# Jooosi Mail Architecture

Jooosi Mail replaces direct WordPress email delivery with a layered runtime built around a compiled Symfony container, attribute-driven discovery, Symfony Mailer, Symfony Messenger, a broad built-in provider catalog, and an initial React admin app.

The architecture is designed to keep WordPress integration thin while making delivery, routing, queueing, and webhook handling explicit domain concerns.

## Design Goals

- Normalize all email requests into a stable internal contract.
- Keep service registration declarative through attributes and discovery.
- Separate transport delivery, routing, queueing, and persistence responsibilities.
- Keep provider-specific behavior at the profile and webhook-adapter edges.
- Make operational state durable through database-backed records.

## System Overview

### Bootstrap Layer

- `jooosi-mail.php` is the plugin entry point.
- `src/Bootstrap/Plugin.php` owns the singleton boot path.
- `src/Bootstrap/Kernel.php` builds and boots the runtime container.
- `src/Bootstrap/LifecycleManager.php` runs activation tasks and registers WordPress bridges.

### Discovery and Registration Layer

- `src/Discovery` defines the attributes, internal PSR-4 scanner, and manifest used by the runtime.
- `ContainerFactory` builds the compiled container and injects the discovery output.
- WordPress registrars attach hooks, REST routes, and WP-CLI commands from discovered services.

### Mail Domain Layer

- `src/Mail/WordPress` converts `wp_mail()` payloads into internal value objects.
- `src/Mail/Profile` defines connection profiles, structured configuration rules, and runtime transport builders for core transports, Symfony bridge transports, and custom provider integrations.
- `src/Mail/Connection` manages persisted connection records.
- `src/Mail/Routing` decides delivery mode, candidate ordering, availability, and health.
- `src/Mail/Delivery` creates Symfony emails and executes transport delivery.
- `src/Mail/Logging` stores mail logs and per-connection attempts, then applies the configured retention policy once logs reach a terminal state.

### Queue Layer

- `src/Queue/Bus` wires Symfony Messenger for Jooosi Mail.
- `src/Queue/Transport` persists queued envelopes in plugin tables.
- `src/Queue/Worker` processes queued messages inside WordPress-friendly time and batch limits.
- `src/Queue/Retry` centralizes retry decisions and retry delays.
- `src/Queue/Trigger` schedules Action Scheduler wakeups for immediate processing and fallback recovery, and can best-effort nudge Action Scheduler's internal async runner to reduce idle-site latency.
- `src/Queue/Worker/WorkerRunner` enforces a single HTTP-triggered worker chain and can queue one continuation wakeup when ready work remains.

### Webhook Layer

- `src/Webhook/Controller` exposes REST ingestion endpoints.
- `src/Webhook/Adapter` isolates provider parsing and verification, with provider-specific adapters layered over a generic fallback parser.
- `src/Webhook/Event` persists normalized events and projects them back into WordPress hooks.

### Admin Layer

- `src/Admin/Menu` registers the top-level WordPress admin page and enqueues the admin app.
- `src/Admin/Controller` exposes admin REST endpoints for dashboard metrics, connections, settings, mail logs, queue logs, webhook logs, and test email sending.
- `resources/admin` and `resources/pages` implement the hash-routed React admin interface for dashboard, connections, logs, settings, and about screens.

## Runtime Flows

### Boot Flow

1. WordPress loads `jooosi-mail.php`.
2. `Plugin` boots the `Kernel`.
3. The container cache metadata is checked against the current source hash.
4. If the cached class is stale, missing, or invalid, discovery reruns and a new compiled container is written.
5. Discovery output is rehydrated into a manifest.
6. Lifecycle services register WordPress hooks, REST routes, and CLI commands.

### Mail Flow

1. `WpMailInterceptor` receives `pre_wp_mail`.
2. `WpMailPayloadNormalizer` builds a `MailRequest`.
3. `RoutingPolicyResolver` produces a `DeliveryPlan`.
4. `MailLifecycleLogger` creates a mail-log row used for both observability and queued delivery payload storage.
5. Jooosi Mail either calls `DeliveryService` immediately or dispatches `SendEmailMessage` to the async transport.
6. When delivery becomes terminal, log retention cleanup may delete the mail-log and attempt rows according to settings.

### Queue Flow

1. Async envelopes are written to the queue table.
2. Action Scheduler queues an immediate wakeup when needed, best-effort nudges its internal async runner, and keeps a recurring fallback run scheduled on a separate hook.
3. `WorkerRunner` ensures only one scheduled worker is active at a time and can queue one follow-up wakeup if ready work remains.
4. `QueueWorker` releases stale claims, keeps claiming ready rows while its time budget remains, and dispatches them through the Messenger bus with `ReceivedStamp`.
5. `SendEmailHandler` calls `DeliveryService`.
6. The worker acknowledges, reschedules, or rejects the message based on retry rules.

### Webhook Flow

1. Provider callbacks hit `/wp-json/jooosi-mail/v1/webhook/{connection_id}`.
2. `WebhookAdapterRegistry` resolves the best adapter for the connection.
3. The adapter verifies and parses the request.
4. Jooosi Mail persists normalized webhook events.
5. Event projection and listeners feed delivery feedback back into routing health.

Operational visibility for dashboard metrics, recent delivery attempts, queue work, and webhook events is exposed through both the admin UI and WP-CLI commands backed by the same persisted records.

## Persistence Model

Jooosi Mail stores runtime state in plugin tables for durability and observability.

Connection records persist structured profile settings and secrets.

Core records:

- connections
- queue messages
- mail logs
- mail attempts
- webhook events

Routing-state records:

- connection circuit breakers
- connection rate limits
- weighted round robin state when smooth WRR is enabled

This keeps queue and routing behavior stable across separate workers and repeated requests. Mail-log rows are retained at least until delivery reaches `sent` or `failed`, because queued delivery reconstructs the message from the persisted mail payload.

## Routing Model

Routing combines:

- preferred connection hints,
- default connection preference,
- strategy selection,
- health scoring,
- circuit-breaker availability,
- rate-limit availability,
- weighted-random or smooth weighted round robin primary selection,
- ordered failover.

The current routing model is intentionally pragmatic. It optimizes for reliability and operability more than for policy richness.

## Extension Model

The runtime is designed to grow through discovered classes rather than manual registries.

Supported extension categories include:

- services,
- hooks,
- controllers,
- routes,
- CLI commands,
- mail profiles,
- transport factories,
- message handlers.

This keeps new functionality close to the feature that owns it and reduces boot-time wiring code.

## Deferred Scope

The architecture intentionally leaves several concerns outside the current core scope:

- admin UI hardening, accessibility polish, and UI-oriented coverage,
- provider-specific setup UX and documentation,
- full webhook and verification parity across every shipped profile,
- richer analytics and monitoring,
- template rendering,
- more advanced routing policies.

Use [`documentation/OPERATIONS.md`](OPERATIONS.md) for runtime workflows and [`documentation/DEVELOPMENT.md`](DEVELOPMENT.md) for contributor guidance.

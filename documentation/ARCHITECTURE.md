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
- `src/Mail/Submission` owns routing and submission of normalized requests, including immediate delivery or atomic persistence of the mail payload and queue envelope. The WordPress interceptor owns preemption, enablement, recursion protection, and conversion of failures to the `wp_mail()` result.
- `src/Mail/Profile` defines connection profiles, structured configuration rules, and runtime transport builders for core transports, Symfony bridge transports, and custom provider integrations.
- `src/Mail/Connection` manages persisted connection records and separates primitive input, profile settings, profile secrets, sender policy, validation, and persistence concerns behind the connection manager.
- `src/Mail/Routing` decides delivery mode, candidate ordering, availability, and health.
- `src/Mail/Delivery` creates Symfony emails and keeps `DeliveryService` as the stable facade over log loading/reconciliation, candidate execution, attempt recording, and terminal outcomes.
- `src/Mail/Logging` stores mail logs and per-connection attempts, then applies the configured retention policy once logs reach a terminal state.

### Queue Layer

- `src/Queue/Bus` wires Symfony Messenger for Jooosi Mail.
- `src/Queue/Transport` persists queued envelopes in plugin tables.
- `src/Queue/State` owns queue reads, claim-owned conditional transitions, UTC timestamps, and owner-token leases.
- `src/Queue/Worker` processes queued messages inside WordPress-friendly time and batch limits.
- `src/Queue/Retry` centralizes retry decisions and retry delays.
- `src/Queue/Trigger` schedules Action Scheduler wakeups for immediate processing and fallback recovery, and can best-effort nudge Action Scheduler's internal async runner to reduce idle-site latency.
- `src/Queue/Worker/WorkerRunner` enforces a single HTTP-triggered worker chain and can queue one continuation wakeup when ready work remains.

### Webhook Layer

- `src/Webhook/Controller` exposes a thin REST ingestion endpoint.
- `src/Webhook/Application` owns connection resolution, authorization, provider parsing, mail-log correlation, persistence, and projection orchestration.
- `src/Webhook/Adapter` isolates provider parsing and verification, with provider-specific adapters layered over a generic fallback parser.
- `src/Webhook/Event` persists normalized events, projects them back into WordPress hooks, and defines the shared routing-health severity policy.

### Admin Layer

- `src/Admin/Menu` registers the top-level WordPress admin page and enqueues the admin app.
- `src/Admin/Controller` exposes thin admin REST endpoints for dashboard metrics, connections, settings, mail logs, queue logs, webhook logs, and test email sending.
- `src/Admin/ReadModel`, `src/Admin/Presentation`, `src/Admin/Application`, and `src/Admin/Settings` own database reads, stable API projection, use-case orchestration, validation, and atomic settings updates.
- `resources/admin` and `resources/pages` implement the hash-routed React admin interface for dashboard, connections, logs, settings, and about screens.
- `LogQueryNormalizer` owns shared search, pagination, sorting, date, and list-filter input parsing for the three admin log endpoints. Typed log read models own database filters, and presenters preserve the endpoint response shapes.
- `useAdminQuery` applies only the latest active request to shared admin state, retains existing data during refresh, and waits for pending work before starting another background poll.
- Dashboard date-range changes use the same query identity and cancellation lifecycle, including changes while the first request is pending.
- `useAdminLogQuery` reuses that request lifecycle for all three log tables, cancels obsolete requests on filter/page/refresh changes, and synchronizes server-clamped pagination. Tables own their filters, row projections, and presentation.
- `parseAdminDateTime` treats offset-free API timestamps as UTC before display or queue-age calculations. Explicit timezone offsets retain their meaning; date-only filter inputs retain their existing contract.

### CLI Layer

- `src/Cli` contains discovered WP-CLI facade classes and their command documentation.
- `src/Cli/Application` coordinates connection and migration use cases without producing terminal output.
- `src/Cli/Presentation` owns stable table rows and scalar formatting.

## Runtime Flows

### Boot Flow

1. WordPress loads `jooosi-mail.php`.
2. `Plugin` boots the `Kernel`.
3. The container cache metadata is checked against the current source hash and a context hash of runtime paths, environment, and debug mode. Both hashes determine the generated PHP class name, preventing copied installations from reusing another root's compiled paths in the same process.
4. If the cached class is stale, missing, or invalid, the request acquires the container build lock and checks the cache again.
5. When a rebuild is still required, discovery reruns and the new compiled container is published atomically.
6. Discovery output is rehydrated into a manifest.
7. Lifecycle services register WordPress hooks, REST routes, and CLI commands.

### Mail Flow

1. `WpMailInterceptor` receives `pre_wp_mail` and preserves any earlier non-null result.
2. `WpMailPayloadNormalizer` builds a `MailRequest`.
3. `MailSubmissionService` asks `RoutingPolicyResolver` for a `DeliveryPlan`.
4. For synchronous delivery, it creates a mail-log row and calls `DeliveryService`; the facade delegates loading, reconciliation, per-candidate transport work, attempt persistence, and terminal outcomes.
5. For asynchronous delivery, it creates the mail-log payload and dispatches `SendEmailMessage` with transport, priority, and optional delay stamps in one DBAL transaction. A dispatch failure rolls both records back.
6. After commit, it triggers the queue worker and publishes `a!jooosi-mail/mail:queued`. Queue wakeup failure retains the committed message for later recovery.
7. When delivery becomes terminal, log retention cleanup may delete the mail-log and attempt rows according to settings.

`MailSubmissionService::submit(MailRequest): bool` accepts an already normalized request and returns whether immediate delivery succeeded or queue persistence completed. `submitWithResult()` preserves that behavior while also returning the new mail-log ID for workflows such as manual resend. Exceptions before commit propagate to the caller, and the WordPress bridge reports them through its existing interception-failure hook. Queue wakeup and extension-subscriber failures after commit are published as diagnostics without changing the accepted result.

### Manual Resend Flow

1. An administrator confirms **Resend email** for any retained lifecycle row.
2. The admin REST controller asks `ManualMailResendService` to reconstruct the stored normalized payload.
3. The service removes stale scheduling and message identity headers, records source-log and requesting-user metadata, and applies `f!jooosi-mail/mail:resend.request`.
4. `MailSubmissionService` creates a separate lifecycle row and sends or queues it through current policy.
5. The original lifecycle row remains unchanged, and `a!jooosi-mail/mail:resend.submitted` publishes the source ID, new ID, and acceptance result.

### Queue Flow

1. Async envelopes are written to the queue table.
2. Action Scheduler queues an immediate wakeup when needed, best-effort nudges its internal async runner, and keeps a recurring fallback run scheduled on a separate hook.
3. `WorkerRunner` uses an owner-token lease so only one scheduled worker is active at a time; an expired worker cannot release a replacement owner's lease. It can queue one follow-up wakeup if ready work remains.
4. `QueueWorker` releases stale claims, keeps claiming ready rows through the queue state repository while its time budget remains, and dispatches them through the Messenger bus with `ReceivedStamp`.
5. `SendEmailHandler` calls `DeliveryService`.
6. The worker acknowledges, reschedules, or rejects the message based on retry rules. Terminal rejection writes the final error in the same claim-owned update as the failed status, so an expired worker cannot overwrite a replacement claim's diagnostic state.

Retry decisions inspect the underlying failures wrapped by Messenger. A message stops retrying when every handler failure is unrecoverable; mixed failures remain subject to the existing attempt limit. When failures request explicit positive delays, the longest delay takes precedence over the configured backoff.

### Webhook Flow

1. Provider callbacks hit `/wp-json/jooosi-mail/v1/webhook/{connection_id}`.
2. The application layer resolves the connection and asks `WebhookAdapterRegistry` for the best adapter.
3. The adapter verifies and parses the request.
4. The ingestion service correlates and persists normalized webhook events.
5. Event projection and the shared severity policy feed delivery feedback back into routing health.

Operational visibility for dashboard metrics, recent delivery attempts, queue work, and webhook events is exposed through both the admin UI and WP-CLI commands backed by the same persisted records.

## Persistence Model

Jooosi Mail stores runtime state in plugin tables for durability and observability.

`DatabaseConnectionFactory` selects DBAL's MySQL or SQLite driver from the WordPress database engine. For SQLite, it resolves the active database file through the SQLite PDO exposed by WordPress, then uses DBAL's native `pdo_sqlite` driver. Migration tables are built through DBAL's schema API, and routing-state writes use platform-specific upsert syntax.

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

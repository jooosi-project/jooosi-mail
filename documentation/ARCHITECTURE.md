# Jooosi Mail Architecture

Jooosi Mail adapts WordPress mail to a delivery runtime built on a compiled Symfony container, Symfony Mailer and Messenger, Doctrine DBAL, Action Scheduler, and a React admin app. Attribute discovery connects WordPress entry points to application and domain services.

## Components

- **Bootstrap and discovery:** `jooosi-mail.php` boots `src/Bootstrap/Plugin.php` and `Kernel`. The discovery scanner finds services, hooks, REST routes, commands, mail profiles, transport factories, and message handlers. Registrars attach the WordPress-facing entry points.
- **Mail:** `src/Mail/WordPress` adapts `wp_mail()` to a `MailRequest`; `src/Mail/Submission` resolves policy and submits it. Profiles and connections supply transport configuration. Routing selects candidates, and delivery records per-connection attempts and terminal outcomes.
- **Queue:** Symfony Messenger envelopes are persisted through the database transport. Queue state services own claims and conditional transitions; workers apply retry policy; Action Scheduler supplies wakeups and recovery runs.
- **Webhooks:** the REST controller delegates connection resolution, verification, parsing, persistence, correlation, and projection to application services and provider adapters. Projected feedback can affect routing health.
- **Alerts:** terminal mail failure triggers the configured channel drivers in `src/Alert`; Telegram, Discord, and Slack are built in.
- **Admin and CLI:** WordPress adapters delegate to application services, read models, and presenters. `resources/admin` and `resources/pages` implement the hash-routed dashboard, connections, logs, settings, and about screens.

## Boot and Cache

The kernel checks compiled container metadata against source and runtime context, including paths, environment, and debug mode. The hashes determine the generated class identity. A missing, stale, or invalid cache is rebuilt under a lock and published atomically; discovery output becomes the runtime manifest. This avoids reusing paths from a copied installation and serializes concurrent rebuilds.

## Mail Submission

1. The `pre_wp_mail` interceptor respects an earlier non-null result and, when enabled, normalizes the WordPress payload.
2. `MailSubmissionService` resolves the delivery mode, strategy, priority, delay, and preferred connection.
3. Sync delivery creates a mail log and invokes `DeliveryService`. Async delivery persists the mail log and Messenger envelope in one DBAL transaction.
4. After an async commit, a notifier triggers the queue and publishes `a!jooosi-mail/mail:queued`. A wakeup or observer failure is diagnostic and does not undo an accepted submission.
5. Delivery records attempts and an eventual terminal result. Retention cleanup can remove terminal mail logs and attempts according to settings.

A successful async submission means the message is durably queued; provider delivery happens later. Scheduled mail uses the queue even when the default delivery mode is synchronous. Admin compose and WP-CLI submit through the same delivery services. Test email goes through `wp_mail()`.

Manual resend reconstructs a retained payload, removes stale scheduling and identity headers, and submits a new lifecycle record using current policy. The source log remains unchanged. Filters and actions expose compose and resend extension points; see [DEVELOPMENT.md](DEVELOPMENT.md).

## Routing and Queue

Routing filters enabled connections by circuit-breaker and rate-limit availability, then considers health, priority, weight, and preferred or default hints. `single` chooses one candidate; `failover`, `weighted_random`, and smooth weighted `round_robin` can retain further candidates for failover. Pending messages use current routing defaults at delivery time.

Action Scheduler triggers immediate wakeups and recurring fallback runs. An owner-token lease keeps scheduled HTTP worker chains single-owner; an expired worker cannot release a replacement owner's lease. Workers release stale claims, claim ready messages in batches, dispatch them through Messenger, and acknowledge, reschedule, or reject each claim. Conditional transitions prevent a worker that lost its claim from overwriting a replacement worker's state.

Retry decisions inspect underlying Messenger failures. Entirely unrecoverable handler failures stop retrying; mixed failures remain subject to the configured attempt limit. Explicit positive delays take precedence over configured backoff, using the longest requested delay.

## Webhooks and Alerts

Provider callbacks use `/wp-json/jooosi-mail/v1/webhook/{connection_id}`. The selected adapter verifies and parses the request, then ingestion correlates and stores normalized events. Verification differs by provider and configuration; `webhook:status` exposes the current posture. Events are projected into WordPress hooks and the shared routing-health policy. Replayed callbacks are not deduplicated and may apply health feedback more than once.

`MailFailureAlertListener` reacts to terminal mail failure. `AlertNotifier` sends to enabled, configured channel drivers; test alerts are sent synchronously. Alert credentials are encrypted by the same secret cipher used for connection secrets.

## Persistence

DBAL uses MySQL or SQLite according to the WordPress database engine. For SQLite, the connection factory resolves the active database file through WordPress's SQLite PDO. Migrations use DBAL's schema API, with platform-specific upserts for routing state.

Plugin tables hold connections, mail logs and attempts, queue messages and attempts, webhook events, circuit breakers, rate limits, and weighted round robin state. Pending mail retains its payload until delivery becomes terminal. Log retention can remove terminal mail logs and attempts, detach webhook events from deleted logs, and prune terminal queue-attempt history.

## Extension Boundaries

Keep provider protocols in profiles, transport factories, transports, and webhook adapters. Controllers and commands translate framework input and output; application services coordinate use cases; repositories and read models own persistence; presenters own response shapes. The admin query hooks share cancellation, polling, and pagination handling, and API timestamps without offsets are parsed as UTC.

See [OPERATIONS.md](OPERATIONS.md) for configuration and diagnostics, [MAIL_PROVIDERS.md](MAIL_PROVIDERS.md) for provider work, and [ROADMAP.md](../ROADMAP.md) for planned changes.

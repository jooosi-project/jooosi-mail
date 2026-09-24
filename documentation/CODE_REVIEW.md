# Architecture and code review — 19 September 2026

This review covers the mail and queue lifecycle, container and WordPress integration, admin REST endpoints, frontend request state, and validation workflow. It includes the earlier corrections below and a follow-up architecture refactor. Provider-by-provider protocol verification, a complete security audit, and a visual accessibility review remain outside this pass.

## Architecture assessment

The existing module layout is worth retaining. Attribute discovery and the compiled container provide a clear composition point; profiles and webhook adapters contain provider-specific behavior; queue claims and durable records coordinate separate WordPress requests. The highest-value changes are at responsibility boundaries, where duplicated orchestration or incomplete state ownership can change delivery behavior.

- **Submission:** the WordPress interceptor previously owned normalization, routing, transaction management, queue stamps, wakeups, and failure translation. `MailSubmissionService` now owns normalized submission. The interceptor depends on four services instead of nine and retains its WordPress-specific contract.
- **Admin requests:** tables should own filters and presentation while shared hooks own request identity, cancellation, polling, and page synchronization. The three log tables now use this boundary without changing their REST response shapes.
- **Runtime identity:** source content alone cannot identify compiled services containing filesystem paths. Cache validity and generated class identity now include the runtime context, while retaining a separate source hash for diagnostics.
- **Queue state:** terminal status and its error belong to the same claim-owned persistence transition. Worker-level orchestration now passes the final failure into that transition.

No schema migrations or dependency changes were needed. Keep future extraction focused on shared rules or state transitions with concrete failure cases; stable endpoint projections now live in presenters close to their read models.

## Completed changes

| Area | Finding and resulting behavior | Coverage |
| --- | --- | --- |
| WordPress mail | The late `pre_wp_mail` interceptor overwrote earlier results. It now returns an existing true/false result before creating logs, queue rows, or delivery attempts. | Earlier true/false results with both sync and async configuration. |
| Queue retries | Messenger wraps handler exceptions, hiding explicit delays and unrecoverable failures from the retry policy. The decider now inspects every nested failure, honors the longest positive delay, and preserves the attempt budget for retryable or mixed failures. | Real Messenger middleware, nested/multiple failures, explicit delays, configured backoff, and exhausted attempts. |
| Admin requests | Older requests could replace newer data/errors or clear a pending request's loading state. `useAdminQuery` now guards response updates by request identity and effect lifetime, and skips overlapping background polls. | Six browser regressions using React, including Strict Mode, slow polling, and cleanup. |
| Log queries | Three controllers repeated date, sort, list-filter, and pagination parsing; nested input caused array-to-string warnings. A discovered `LogQueryNormalizer` now owns these rules and ignores non-scalar input. Endpoint-specific SQL and response shapes stay in their controllers. | All three REST endpoints: malformed inputs, valid list filters, day boundaries, page clamping, page-size caps, and empty results. |
| Database hosts | Splitting `DB_HOST` at the first colon broke IPv6 and combined port/socket formats. The connection factory now uses WordPress's parser and mysqli IPv6 handling. | Hostnames, IPv4/IPv6, ports, sockets, combined formats, fallback, and lazy DBAL configuration. |
| Mail submission | Added a discovered `MailSubmissionService` and reduced `WpMailInterceptor` to preemption, enablement, recursion protection, normalization, and failure translation. | Sync/async integration, rollback after actual queue insertion, successful submission after failure, priority/delay stamps, commit-before-wakeup-before-notification order, and nested interception. |
| Container relocation | Copied caches retained paths embedded in another installation and could reuse its already loaded PHP class. Source and runtime context now jointly determine class identity; direct loading also rejects mismatched context. | Copied source/cache with identical source hashes, two roots loaded in one PHP process, alternate plugin entrypoint, environment/debug changes, legacy metadata, and cache reuse. |
| Log table loading | Three independent timers superseded responses taking longer than 15 seconds. `useAdminLogQuery` reuses the shared request lifecycle, skips polls while pending, forwards cancellation, and synchronizes clamped pages. | Real React and API-loader regressions across mail, queue, and webhook endpoints. |
| Dashboard loading | Date-range changes now use the shared query identity and cancellation lifecycle, replacing page-level loaded/refresh flags. | TypeScript validation, shared initial-load query-change regressions, and independent consumer review. |
| Admin timestamps | SQL UTC timestamps were parsed as browser-local time. `parseAdminDateTime` now gives offset-free timestamps an explicit UTC offset and is shared by formatting and queue claim-age calculations. | Offset-free SQL/ISO timestamps, explicit offsets, empty/invalid inputs, and non-UTC timezone checks. |
| Terminal queue errors | Final rejection omitted `last_error`. The worker now passes the final error into the existing claim-owned status update, while ordinary Messenger rejection preserves its previous error behavior. | No retries, a final failure different from the preceding retry, and a late failure after another worker reclaimed the message. |

## Follow-up status

The first review's four state-ownership findings are now closed:

| Finding | Result |
| --- | --- |
| Worker lease ownership | Worker and scheduler locks share an owner-token lease with atomic stored-value comparison, expired legacy-lock takeover, and deterministic late-owner coverage. |
| Partial settings updates | PATCH recursively merges supplied fields, malformed sections are rejected, PUT keeps replacement semantics, and one option write publishes the validated settings set. |
| Post-commit notification failures | Queue wakeup and `mail:queued` observer failures are diagnostic; a committed message remains accepted. |
| Per-connection health samples | Mail attempts and webhook events now take a bounded latest-N sample independently for each connection. |

The rebuild also moved admin SQL and projection into typed read models/presenters, decomposed delivery behind its existing facade, separated webhook ingestion services, thinned connection and migration CLI adapters, and split container cache identity, artifact IO, and locking without changing public contracts.

## Remaining findings

1. **P2 — Duplicate webhook callbacks apply health feedback repeatedly.** [`WebhookEventRepository::save()`](../src/Webhook/Event/WebhookEventRepository.php) always inserts, and the webhook ingestion service projects every saved event. Replayed callbacks can accumulate duplicate penalties. Define provider-specific idempotency keys, including behavior when event IDs are absent, before adding a uniqueness migration and concurrent-replay coverage. Current duplicate behavior is explicitly characterized so this requires a deliberate schema change.

2. **P2 — Webhook log availability depends on an unused overview request.** [`WebhookLogsPage`](../resources/pages/logs-webhooks.tsx) loads and polls `/admin/logs`, blocks rendering until it succeeds, and then displays none of its data. The table separately loads `/admin/logs/webhooks`. Let the table's query own availability and refresh feedback so an unrelated overview failure cannot block this page.

3. **P2 — Automated validation is missing from the checked-in workflow.** The only file under [`.github/workflows`](../.github/workflows) is a tag-triggered deployment job. It builds assets but does not run the PHP integration suite or browser regressions. Add a pull-request validation workflow with the installed TypeScript check, isolated PHP tests, and headless browser tests. The development guide now describes the actual state.

Two smaller contract issues also deserve follow-up: [`QueueCommand`](../src/Cli/QueueCommand.php) describes `--limit` as a total maximum although it controls claim batch size, and [`HandlerLocator`](../src/Queue/Bus/HandlerLocator.php) ignores the transport restriction declared by `MessageHandler`. Preserve scheduled draining while clarifying the CLI option; test transport-specific handler selection before adding another message transport.

## Validation

- PHP integration suite inside a freshly recreated isolated PHP 8.1 WordPress environment: **279 tests, 1,825 assertions, one skipped**. The container concurrency test requires PCNTL, which is unavailable in that image.
- Frontend request and UTC regressions: **16 cases passed in each of UTC, Asia/Jakarta, and America/Los_Angeles (48 executions)** using `node tests/Frontend/run-browser-tests.mjs`. The tests assert the browser's actual timezone and cover both orderings of page selection and poll completion in one render batch.
- TypeScript: **passed** for the application with `node node_modules/typescript/bin/tsc --noEmit`, and for browser tests with `node node_modules/typescript/bin/tsc --ignoreConfig --noEmit --strict --skipLibCheck --target ES2023 --module ESNext --moduleResolution bundler --jsx react-jsx tests/Frontend/use-admin-query.browser.tsx`.
- Focused compatibility review covered cache identity, service constructor facades, REST and CLI projections, queue lease ownership, delivery lifecycle hooks, connection secrets, settings persistence, webhook behavior, and frontend request/pagination ownership. The isolated test database was stopped after validation.
- No application production build or Git operations were run.

The next persistence-focused refactor should define provider event identity before adding webhook replay deduplication. Database migration internals and the remaining large CLI adapters can continue moving behind stable facades independently.

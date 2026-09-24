# Backend Rebuild

Jooosi Mail is being rebuilt internally behind its existing public contracts. The top-level architecture remains unchanged: WordPress adapters call application services, domain services coordinate mail and queue behavior, and infrastructure services own persistence and framework integration.

A delete-and-replace rewrite is intentionally avoided because existing installations contain durable connection, mail, queue, migration, and webhook state. Externally supported REST, CLI, hook, schema, and provider contracts remain stable. Long-lived service facades that integrations may instantiate, including delivery, webhook, connection-input, CLI, and container-cache entry points, retain their positional constructors while their responsibilities move behind them.

## Compatibility Boundary

The rebuild must preserve:

- database tables, columns, migration versions, statuses, and serialized payloads;
- REST paths, methods, permissions, error codes, status codes, and response casing;
- WP-CLI command names, aliases, arguments, and output shapes;
- WordPress hook names, argument order, filter fallback behavior, and priorities;
- provider profile keys, DSN schemes, transport factories, webhook adapters, and discovery attributes;
- `MailRequest`, `DeliveryPlan`, queue-envelope, and connection configuration wire formats;
- sync and async delivery semantics, including atomic mail-log and queue persistence;
- manual resend for every retained lifecycle status without mutating the source log.

Provider transports and webhook adapters stay as explicit leaf implementations. Their protocol differences are capability, not architectural duplication, and are migrated to shared helpers only when payload and error-response tests exist.

## Dependency Direction

```text
WordPress REST / hooks / CLI
            |
            v
Application services and read models
            |
            v
Mail, queue, routing, and webhook domain services
            |
            v
Repositories, Symfony adapters, DBAL, and WordPress infrastructure
```

Controllers and commands may translate framework input and output. They must not own SQL, state transitions, provider rules, or lifecycle orchestration.

Repositories own persistence and conditional state changes. Presenters own stable API projections. Value objects own immutable state. Cross-cutting policies must have one source of truth.

## Implemented Slices

### Admin

- Mail, queue, and webhook log controllers delegate filtering, sorting, pagination, SQL, and projection to typed read models and presenters.
- Connection CRUD delegates request normalization, use-case orchestration, secret-safe projection, and operational status to dedicated services.
- Dashboard and overview delegate aggregate reads, date-bucket SQL, operational status normalization, and row projection to read models and presenters.
- Settings reads, supported choices, validation, and persistence are separate services behind a thin REST controller.
- PATCH merges only supplied nested values; PUT retains replacement semantics.
- Settings are persisted with one option update instead of a sequence of partial writes.

### Mail

- `MailRequest` and `Connection` expose immutable copy helpers, removing error-prone constructor reconstruction.
- Manual resend and sender policy use those immutable boundaries while retaining all headers, metadata, and source-log guarantees.
- `DeliveryService` remains the delivery facade while log loading and reconciliation, candidate execution, attempt persistence, and terminal outcomes are isolated collaborators.
- Connection input keeps its original facade while primitive normalization, profile settings, profile secrets, and sender policy input are resolved independently.
- Queue wakeup and `mail:queued` notifications run through a post-commit notifier. Observer failures are diagnostic and cannot turn a durably queued message into a rejected submission.

### Queue

- Queue reads and conditional state transitions are centralized in a queue state repository.
- Claim creation and UTC timestamps are explicit collaborators.
- Worker and scheduling locks share an owner-token lease service.
- Expired takeover and release use an atomic stored-value comparison, so an old worker cannot delete a newer owner's lease.

### Webhooks and routing health

- The webhook controller delegates connection resolution, authorization, provider parsing, mail-log correlation, persistence, and projection to application services.
- Webhook event penalties and circuit-breaker effects use one severity policy.
- Mail-attempt and webhook health samples are bounded independently for each connection, preventing a busy connection from hiding a quieter connection's failures.

### CLI

- Connection and migration commands remain discovered WP-CLI facades while application services own use-case orchestration and presenters own stable output rows.
- Common scalar, boolean, format, and identifier normalization is shared instead of repeated inside commands.

### Infrastructure

- `ContainerCache` keeps its public API and generated class identity while signature calculation, artifact IO, and re-entrant build locking are isolated collaborators.
- Cache metadata, reason codes, filenames, atomic publication, runtime-path invalidation, and cross-process serialization remain unchanged.

## Next Slices

1. Separate migration planning, execution, status reads, and schema bootstrap inside the database layer without changing migration history.
2. Thin the remaining queue, mail, and webhook CLI adapters around shared application services where characterization coverage exists.
3. Add webhook deduplication only after provider event identity and migration behavior are specified and tested.
4. Move provider transports onto shared HTTP, address, attachment, and response helpers one provider at a time.
5. Add pull-request validation for the isolated PHP suite, frontend type checks, and browser regressions.

## Verification Rule

Every slice requires characterization coverage before private logic is removed. The final integration gate is the WordPress-backed PHP suite; frontend type and browser checks remain required when an API response or admin behavior changes. No schema or public-contract change may be hidden inside a cleanup slice.

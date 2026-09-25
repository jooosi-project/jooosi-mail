# Jooosi Mail Development

This guide is for contributors working on the Jooosi Mail codebase.

## Local Setup

Requirements:

- PHP 8.1+
- WordPress 6.8+
- Composer
- Node.js
- `pnpm`

Typical setup:

```bash
composer install
pnpm install
```

After dependencies are installed, use `wp-env` for development and testing. Open `wp-admin/admin.php?page=jooosi-mail` and use WP-CLI for smoke testing.

Useful commands:

```bash
wp jooosi-mail connection:profiles
wp jooosi-mail connection:status --all
wp jooosi-mail webhook:status --all=true
wp jooosi-mail queue:status
wp jooosi-mail queue:failed
wp jooosi-mail mail:test --to=you@example.com
wp jooosi-mail mail:test --to=you@example.com --connection-id=3
pnpm dev
pnpm check
pnpm build
pnpm wp-env:start
pnpm wp-env:test:start
pnpm test:php:docker
```

Run frontend validation and WordPress-backed PHP integration tests before submitting changes. The current GitHub Actions configuration contains the tag-triggered deployment workflow; a pull-request validation workflow remains planned. Prepare a release with `pnpm run release -- <major.minor.patch>` (requires Composer), review the generated commit and tag, then push both to trigger the deployment workflow.

## Docker Test Workflow

The repository also includes committed `wp-env` configs for a containerized WordPress workflow.

Useful commands:

```bash
pnpm wp-env:start
pnpm wp-env:status
pnpm wp-env:test:start
pnpm wp-env:test:status
pnpm test:php:docker
pnpm wp-env:stop
pnpm wp-env:test:stop
pnpm wp-env:destroy
pnpm wp-env:test:destroy
```

Notes:

- `vp-wp` is only used for frontend asset tooling. It does not provide a WordPress test environment.
- `wp-env` is the preferred isolated workflow because it is WordPress-native, mounts the plugin automatically, and already includes `composer`, `phpunit`, and `wp-cli` inside the container.
- `.wp-env.json` is the normal development environment. `.wp-env.test.json` is the isolated environment used by `pnpm test:php:docker`.
- A custom Docker stack would only be worth the extra maintenance if the project later needs tighter image control, service customization, or a broader CI matrix.

## Frontend Query Regression Tests

Run `node tests/Frontend/run-browser-tests.mjs` to exercise `useAdminQuery`, `useAdminLogQuery`, the log API loaders, and UTC timestamp parsing with real React in headless Chrome. Set `CHROME_PATH` if Chrome is not installed in a standard location. The runner bundles only the test entry into a temporary directory, uses an isolated browser profile, and removes its generated files afterward. It does not start the application dev server or create a production build.

The tests cover response ordering, loading/error state, retained refresh data, unmounting, Strict Mode, slow polling, request cancellation, manual refresh, and server-clamped pagination across all three log endpoints. Continue to run `pnpm check` for application TypeScript validation.

The browser runner repeats the cases in UTC, Asia/Jakarta, and America/Los_Angeles and verifies that Chrome actually uses each requested timezone.

## Code Organization

- `src/Bootstrap` - plugin boot, lifecycle, paths, environment, kernel
- `src/Discovery` - attributes, discovery, runtime manifest
- `src/Infrastructure` - container signatures/artifacts/locks, database, security, and WordPress bridge services
- `src/Mail` - normalization, submission, profiles, connection input, routing, logging, and delivery lifecycle collaborators
- `src/Queue` - bus, messages, state repositories, owner leases, transport, retry, workers, and triggers
- `src/Webhook` - application ingestion services, provider adapters, the REST controller, persistence, and event projection
- `src/Cli` - discovered WP-CLI facades plus application and presentation collaborators
- `src/Admin` - thin REST controllers, read models, presenters, application services, settings boundaries, the admin menu, and test-email helpers
- `resources/admin` and `resources/pages` - React admin app routes and screens
- `src/Database/Migration` - schema management

## Key Architectural Conventions

- Use strict types and PHP 8.1+ features.
- Keep runtime discovery attribute-driven through the internal PSR-4 scanner.
- Register WordPress hooks, REST routes, and CLI commands through discovery and registrars.
- Keep provider-specific behavior in profiles and webhook adapters.
- Use Doctrine DBAL-backed repositories for persistence.
- Keep WordPress controllers, hooks, and CLI commands as adapters. Put SQL in repositories/read models, stable output shapes in presenters, and use-case coordination in application/domain services.
- Preserve supported REST, CLI, hook, schema, payload, provider, and queue contracts while refactoring. Long-lived facades should retain positional constructor compatibility by adding optional collaborators at the end.
- Treat mail requests, delivery plans, and queue messages as stable internal contracts.
- Keep normalized submission orchestration in `MailSubmissionService`; WordPress interception should only adapt the hook contract. Persist the mail log and queue envelope together, and trigger workers after commit.
- Use `useAdminLogQuery` for paginated log loaders, and forward its abort signal through the API client. Parse API timestamps through `parseAdminDateTime` before interpreting their time or age.
- Use `wp jooosi-mail connection:profiles` and `wp jooosi-mail webhook:status` as the fastest way to confirm the live feature surface before changing docs.

## Extension Points

### Customize Manual Resends

Manual resend reconstructs a retained `MailRequest`, removes stale `Date`, `Message-ID`, and `X-Schedule-Time` headers, then submits a new lifecycle entry through the current routing policy. The source log is never mutated.

Use `f!jooosi-mail/mail:resend.request` to replace the reconstructed request before submission. The filter receives the request and source mail-log ID:

```php
use JooosiMail\Mail\ValueObject\MailRequest;

add_filter(
    'f!jooosi-mail/mail:resend.request',
    static function (MailRequest $mailRequest, int $sourceMailLogId): MailRequest {
        return $mailRequest;
    },
    10,
    2,
);
```

After submission, `a!jooosi-mail/mail:resend.submitted` receives the source mail-log ID, new mail-log ID, and acceptance status. Exceptions from action subscribers are logged without changing the already-completed submission result.

### Add a New Profile

To add a new mail profile:

1. Create a class under `src/Mail/Profile`.
2. Mark it with `#[Service]` and `#[MailProfile(...)]`.
3. Implement profile metadata and runtime transport building from stored configuration.
4. Keep structured configuration in connection settings and secrets.
5. Update the operations documentation if the profile becomes user-facing.

### Add a New Webhook Adapter

To add a provider adapter:

1. Create a concrete adapter under `src/Webhook/Adapter`.
2. Mark it as a discovered service.
3. Implement `supports()`, `parse()`, and `verify()`.
4. Keep provider parsing isolated inside the adapter.
5. Feed normalized events into the shared webhook event model.

### Add a New Queue Handler or Message

To extend async processing:

1. Create a message class under `src/Queue/Message`.
2. Create a handler marked with `#[MessageHandler(...)]`.
3. Route the message through the Messenger bus.
4. Keep failure behavior compatible with the retry system.

### Add a New WP-CLI Command

To add a command:

1. Create a service in `src/Cli`.
2. Mark it with `#[Command(...)]`.
3. Keep the command small and delegate domain behavior to a service class.
4. Update the operations documentation when the command becomes part of the supported workflow.

## Development Workflow Expectations

- Update docs when behavior or operational workflows change.
- Update `CHANGELOG.md` for notable changes.
- Update `ROADMAP.md` when priorities move.
- Prefer small, runtime-safe additions unless the task specifically requires broader UI or product changes.

## Current Gaps

- Admin UI exists, but polish, accessibility review, provider setup guidance, and UI-oriented coverage are still ongoing
- Provider setup and troubleshooting docs still lag behind the shipped profile catalog
- Some profiles are still transport-only and do not yet have matching webhook ingestion or verification support
- Automated PHP coverage is still centered on WordPress integration paths such as plugin boot, activation migrations, connection/migration/queue/webhook/mail CLI behavior, `wp_mail()` interception, queue processing, webhook handling, routing behavior, config persistence, and mail payload normalization

Use [`ROADMAP.md`](../ROADMAP.md) for planned work and [`CHANGELOG.md`](../CHANGELOG.md) for recorded changes.

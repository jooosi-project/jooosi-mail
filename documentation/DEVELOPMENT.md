# Jooosi Mail Development

## Setup

The plugin declares WordPress 7.0+ and PHP 8.1+; encrypted credentials require PHP Sodium. Install Composer, Node.js, and pnpm for development. The release workflow uses Node 26 and pnpm 12.

```bash
composer install
pnpm install
pnpm wp-env:start
pnpm dev
```

`wp-env` requires Docker and mounts the plugin into WordPress. Open `wp-admin/admin.php?page=jooosi-mail`. The dev server provides frontend assets with HMR; use `pnpm build` when production assets are needed. `vp-wp` provides asset tooling, not a WordPress environment.

The committed `.wp-env.json` is for development; `.wp-env.test.json` is a separate test environment. Their initial ports are 8888 and 8889 respectively, with automatic port selection enabled. Use `pnpm wp-env:status` or `pnpm wp-env:test:status` to inspect them; matching `:stop` and `:destroy` scripts are available.

## Validation

```bash
pnpm check                                  # TypeScript
composer test:rector                        # PHP downgrade-rule fixtures
pnpm test:php:docker                        # Isolated WordPress integration suite
node tests/Frontend/run-browser-tests.mjs   # React request and timezone regressions
```

`pnpm test:php:docker` starts the test `wp-env`, installs Composer dependencies in its CLI container, and runs `composer test:php`. To run the WordPress-backed suite locally, configure the WordPress PHPUnit library with a WordPress installation and disposable test database, then run `composer test:php`. `tests/bootstrap.php` uses `WP_TESTS_DIR` if set, otherwise `vendor/wp-phpunit/wp-phpunit`; the Composer-supplied test configuration loads the file specified by `WP_PHPUNIT__TESTS_CONFIG`.

The frontend runner needs Chrome or Chromium; set `CHROME_PATH` if it is not in a standard location. It bundles a temporary test entry and checks request ordering, polling, cancellation, pagination, and UTC parsing in UTC, Asia/Jakarta, and America/Los_Angeles. It does not start the dev server or build production assets.

PHP integration coverage spans boot, migrations, CLI, mail, queue, routing, webhooks, connections, settings, and admin endpoints. It is not a complete provider or UI matrix. The checked-in GitHub Actions workflow deploys tagged releases; pull-request validation is not yet configured.

## Code Organization

- `src/Bootstrap`, `src/Discovery`, `src/Infrastructure` — boot, attribute discovery, container, persistence, security, and WordPress bridges.
- `src/Mail`, `src/Queue`, `src/Webhook` — submission, profiles, connections, routing, delivery, durable queueing, and provider callbacks.
- `src/Alert` — failure alert configuration, notification, and channel drivers.
- `src/Admin`, `src/Cli` — WordPress admin and WP-CLI adapters, application services, read models, and presenters.
- `src/Database/Migration` — schema versions and migration services.
- `resources/admin`, `resources/pages` — hash-routed React admin app.
- `tests/Integration`, `tests/Frontend`, `tests/Rector` — PHP integration, browser regression, and downgrade-rule tests.

See [ARCHITECTURE.md](ARCHITECTURE.md) for runtime flows and [AGENTS.md](../AGENTS.md) for project conventions.

## Extension Points

Services, controllers, routes, hooks, CLI commands, profiles, transport factories, and message handlers are discovered through attributes. Keep WordPress controllers and commands thin; put persistence in repositories or read models and lifecycle coordination in application or domain services. Preserve existing REST, CLI, hook, schema, provider, payload, and queue contracts when refactoring. Keep positional constructors of long-lived service facades compatible; add new collaborators as optional trailing arguments.

### Mail Submission Hooks

Manual resend reconstructs a retained `MailRequest`, removes stale `Date`, `Message-ID`, and `X-Schedule-Time` headers, and submits a new lifecycle entry. The filter receives the request and source log ID:

```php
use JooosiMail\Mail\ValueObject\MailRequest;

add_filter(
    'f!jooosi-mail/mail:resend.request',
    static function (MailRequest $request, int $sourceMailLogId): MailRequest {
        return $request;
    },
    10,
    2,
);
```

`a!jooosi-mail/mail:resend.submitted` receives the source log ID, new log ID, and acceptance status. Admin compose exposes `f!jooosi-mail/mail:compose.request` with the request and user ID, and `a!jooosi-mail/mail:compose.submitted` with the log ID, user ID, and acceptance status. Subscriber failures after submission are logged without changing the completed result.

### Providers and Webhooks

Follow [MAIL_PROVIDERS.md](MAIL_PROVIDERS.md) for profile, transport, webhook, test, and documentation guidance. Check `wp jooosi-mail connection:profiles` and `wp jooosi-mail webhook:status --all=true` against a running installation.

### Alert Channels

Implement `AlertChannelDriverInterface` in a discovered `#[Service]` under `src/Alert/Channel`. Give it a stable key, credential and optional target fields, validation, and delivery behavior; use `AlertHttpClient` for outbound JSON. Add the corresponding labels and setup instructions in `resources/components/alert-settings.tsx`. Drivers are registered automatically; keep released option field names stable.

### Queue Messages and CLI Commands

Add queue messages under `src/Queue/Message`, mark handlers with `#[MessageHandler(...)]`, and preserve retry semantics. Add WP-CLI adapters under `src/Cli` with `#[Command(...)]`; delegate domain work to services and document supported commands in [OPERATIONS.md](OPERATIONS.md).

## Releases and Documentation

Record notable changes under **Unreleased** in [CHANGELOG.md](../CHANGELOG.md), keep [ROADMAP.md](../ROADMAP.md) current, and update the relevant user and developer guides with behavior changes.

With a clean working tree, `pnpm run release <major.minor.patch>` updates release metadata and the WordPress readme, refreshes the Composer lock file, then creates a Git commit and tag. Review those changes before pushing the commit and tag. The tag-triggered deployment workflow builds and packages the release.

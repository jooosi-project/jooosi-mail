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

Pull requests and pushes to `main` run the same frontend validation and WordPress-backed PHP integration tests in GitHub Actions. Prepare a release with `pnpm run release -- <major.minor.patch>`, review the generated commit and tag, then push both to trigger the deployment workflow.

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

## Code Organization

- `src/Bootstrap` - plugin boot, lifecycle, paths, environment, kernel
- `src/Discovery` - attributes, discovery, runtime manifest
- `src/Infrastructure` - container, database, security, and WordPress bridge services
- `src/Mail` - normalization, profiles, connections, routing, logging, delivery
- `src/Queue` - bus, messages, transport, retry, worker, triggers
- `src/Webhook` - adapters, controller, persistence, event projection
- `src/Cli` - WP-CLI commands
- `src/Admin` - admin menu, REST controllers, authorization, connection payloads, and test-email admin helpers
- `resources/admin` and `resources/pages` - React admin app routes and screens
- `src/Database/Migration` - schema management

## Key Architectural Conventions

- Use strict types and PHP 8.1+ features.
- Keep runtime discovery attribute-driven through the internal PSR-4 scanner.
- Register WordPress hooks, REST routes, and CLI commands through discovery and registrars.
- Keep provider-specific behavior in profiles and webhook adapters.
- Use Doctrine DBAL-backed repositories for persistence.
- Treat mail requests, delivery plans, and queue messages as stable internal contracts.
- Use `wp jooosi-mail connection:profiles` and `wp jooosi-mail webhook:status` as the fastest way to confirm the live feature surface before changing docs.

## Extension Points

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

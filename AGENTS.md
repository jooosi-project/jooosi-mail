# Agent Guidelines for JooosiMail

## Code Style
- Use PHP 8.1+ features, strict types (`declare(strict_types=1);`)
- WordPress 6.8+ compatibility
- **WordPress Hooks**: Use WordPress hooks (actions/filters) for extensibility. Hook names should be formatted as `{prefix:a!|f!}jooosimail/{module}[/submodule...]:action[.subaction...]`. (e.g., `a!jooosi-mail/core:send-email`, `f!jooosi-mail/connections/smtp:send.from`, `a!jooosi-mail/profiles:register`, `a!jooosi-mail/webhook:handle.callback`, etc). Where `a!` is for actions and `f!` is for filters.
- **Attributes**: Use PHP 8 attributes for discovery (#[Service], #[Controller], #[Route], #[Hook], #[Command], etc.)
- **Naming**: camelCase for methods, snake_case for WordPress hooks, SCREAMING_SNAKE_CASE for constants
- **Symfony Compat**:Follow the Symfony coding standards as closely as possible while maintaining WordPress compatibility:
    - fetch https://symfony.com/doc/current/contributing/code/standards.html
    - fetch https://symfony.com/doc/current/contributing/code/experimental.html
    - fetch https://symfony.com/doc/current/contributing/code/conventions.html
- Prefer using WordPress functions over native PHP functions where applicable
- **Types**: Strict typing everywhere - return types, param types, property types (including union/intersection when needed)
- **Database**: Use Doctrine DBAL via DatabaseService, never raw SQL without parameterization
- **Dependency Injection**: Constructor injection via Container, services auto-discovered via #[Service] attribute
- **Error Handling**: Throw typed exceptions (RuntimeException, InvalidArgumentException), create custom exceptions as needed, wrap Doctrine exceptions
- **PHP Classes imports**: Discouraged inline fully qualified class names (e.g., `\Some\Namespace\ClassName`), always import with `use Some\Namespace\ClassName;` and refer to it as `ClassName` in code. Aliasing may be used if necessary to avoid name conflicts.
- **Documentation**: PHPDoc for all classes, methods, properties. Use `@since` tags for versioning.
- **Iconography**: use `unplugin-icons` for admin UI icons. `unplugin-icons` is automatically install icon sets when you import them.

## Testing
- WordPress-backed PHPUnit integration coverage is available through `composer test:php`.
- A `wp-env` workflow is available for isolated containerized runs via `pnpm wp-env:test:start` and `pnpm test:php:docker`.
- Coverage is still backend-first and currently focuses on boot, migrations, CLI, queue, routing, webhook, and `wp_mail()` integration behavior rather than a full provider or UI matrix.

## Documentation
- Avoid unnecessary comments, focus on self-explanatory code
- Maintain up-to-date documentation in code comments and external docs
- Use clear, concise language, and as short as possible
- Document public APIs thoroughly with examples where applicable via PHPDoc
- Maintain a changelog for significant changes on the CHANGELOG.md file
- Maintain a roadmap for planned features and improvements on the ROADMAP.md file
- Maintain documentation on the ./documentation folder for user guides, developer guides, and architecture overviews

## Development notes
- Avoid running `pnpm run build` unless necessary. I already have running `pnpm run dev` in background.
- Do not start the dev server; it is already running with HMR.
- Do not run git operations unless requested.

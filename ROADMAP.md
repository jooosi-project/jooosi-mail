# Roadmap

This roadmap tracks planned work; priorities can change. For shipped behavior and release history, see the [documentation](documentation/README.md) and [changelog](CHANGELOG.md).

## Current Focus

- Define provider-specific webhook event identity and migration behavior before deduplicating callbacks and their routing-health feedback.
- Improve admin validation, accessibility, empty and error states, provider setup guidance, and UI coverage.
- Allow webhook logs to load independently of the log overview request.
- Expand webhook ingestion and verification where supported by providers, and improve diagnostics and incident-response guidance.
- Continue separating migration internals and operational CLI adapters behind stable contracts.
- Add pull-request validation for the PHP integration suite, frontend type checking, and browser regressions.

## Later

- Add end-to-end coverage for admin workflows and upgrades or migrations.
- Correct `queue:work --limit` help to describe the claim batch size.
- Test and enforce message-handler transport restrictions before adding another message transport.
- Expand delivery monitoring, provider and domain balancing controls, and routing policies.
- Explore template rendering and richer analytics.
- Expand developer extension guides and add transports as demand warrants.

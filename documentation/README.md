# Jooosi Mail Documentation

This directory contains the living documentation for Jooosi Mail. The root README is intentionally a concise product overview, while these documents keep the deeper setup, runtime, architecture, development, and current-limit details.

Third-party code incorporated into the plugin is documented in [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md).

## Start Here

- [`../README.md`](../README.md) - product overview and entry points
- [`OPERATIONS.md`](OPERATIONS.md) - setup, admin and CLI workflows, queue, routing, webhooks, observability, and current operational limits
- [`ARCHITECTURE.md`](ARCHITECTURE.md) - how the runtime is structured and why
- [`DEVELOPMENT.md`](DEVELOPMENT.md) - local development notes and extension points

## Document Roles

- Overview docs explain what the plugin is and what is currently in scope.
- Operations docs explain how to configure and run the plugin.
- Architecture docs explain system boundaries, data flow, and design choices.
- Development docs explain how to work on the codebase and extend it safely.

## Project References

- [`../ROADMAP.md`](../ROADMAP.md) tracks planned work.
- [`../CHANGELOG.md`](../CHANGELOG.md) records notable changes.

## Live Source Of Truth

- Use the WordPress admin page at `wp-admin/admin.php?page=jooosi-mail` for the current dashboard, connection, settings, mail log, queue log, webhook log, and test-email workflows.
- Use `wp jooosi-mail connection:profiles` to inspect the currently shipped profile catalog, supported schemes, configuration fields, and webhook support.
- Use `wp jooosi-mail webhook:status --all=true` to inspect the webhook adapter and verification posture for real configured connections.

When behavior changes, update the relevant documentation in the same change set so the docs remain trustworthy.

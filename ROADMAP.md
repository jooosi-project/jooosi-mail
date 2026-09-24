# Roadmap

This roadmap tracks the current direction of the project. Jooosi Mail is still in its initial phase, so priorities can move as backend contracts, admin workflows, provider coverage, and operational documentation mature.

## Recently Landed

- Added confirmed manual resending from retained email logs with a fresh lifecycle record and current delivery policy.
- Rebuilt the backend internals behind stable contracts: thin admin and CLI adapters, typed read models and presenters, decomposed delivery and connection input, explicit queue state and owner leases, webhook application services, and split container-cache responsibilities.
- Made settings PATCH updates non-destructive, isolated post-commit observer failures from queue acceptance, and bounded routing-health samples per connection.
- Extracted mail submission orchestration, unified log-table request handling and UTC parsing, preserved terminal queue errors, and made compiled container caches specific to their runtime paths and environment.
- Consolidated admin log query parsing, guarded shared admin request state, and corrected WordPress mail preemption, wrapped queue retry decisions, and database host parsing.
- Expanded the built-in provider catalog across core transports, Symfony bridge transports, and custom integrations including Gmail, Mailomat, Microsoft Graph, SendLayer, SMTP2GO, SparkPost, ToSend, ZeptoMail, and Zoho Mail.
- Hardened webhook verification and parsing for AhaSend, Brevo, Mailgun, Mailjet, Mailomat, MailerSend, Mailtrap, Mandrill, Postmark, Resend, SendGrid, SendLayer, SMTP2GO, SparkPost, Sweego, ToSend, and ZeptoMail.
- Added an initial admin app for dashboard metrics, connection management, settings, email logs, queue logs, webhook logs, and test email sending.
- Expanded the WordPress-backed PHP integration suite across connection, transport, CLI, and webhook flows.

## Current Focus

- Define provider-specific webhook event identity and migration behavior before deduplicating replayed callbacks and routing-health feedback.
- Continue separating migration planning/execution and the remaining operational CLI adapters behind stable facades.
- Harden the initial admin UI with stronger validation, clearer empty/error states, accessibility review, provider setup guidance, and UI-oriented coverage.
- Publish operator and developer documentation for supported providers, webhook setup requirements, and incident-response workflows.
- Refine admin, CLI, and REST troubleshooting flows for queue, routing, delivery, and webhook incidents.
- Close the biggest gaps between the shipped transport catalog and the smaller set of providers with first-class webhook ingestion and verification coverage.

## Next

- Deduplicate provider webhook retries before applying health penalties once the durable identity contract is specified.
- Expand monitoring and operator guides around delivery attempts, queue recovery, and webhook events.
- Improve admin UX for provider-specific configuration, secret handling, scheme selection, and webhook diagnostics.
- Add end-to-end integration coverage for admin flows and upgrade or migration paths.
- Run PHP integration, frontend type checking, and browser regressions in a pull-request validation workflow.

## Later

- Add template rendering, analytics, and richer routing policies.
- Add provider-specific and domain-specific balancing controls.
- Expand developer extension guides.
- Explore lower-priority or non-production transports only when there is clear product demand.

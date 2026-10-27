# Adding or Updating an Email Provider

Use this guide when adding a provider profile, adding another sending method to an existing profile, or changing provider behavior. Keep provider-specific behavior at the profile, transport, and optional webhook-adapter boundaries. Core routing and connection management should not need provider-specific branches.

Code references in this guide are examples of existing implementations, not a required pattern or standard bar. Choose an approach that fits the provider's documented protocol, authentication, and message behavior.

## 1. Confirm the Provider's Sending Methods

Start with the provider's official developer documentation. Record the exact outbound methods and their requirements before changing code:

- API URL, HTTP method, authentication, regional endpoints, request limits, and response shape
- SMTP host, credentials, supported ports, and TLS modes
- Optional template, campaign, batch, or scheduling behavior and how it differs from ordinary message sending
- Supported message features, such as multiple recipients, Cc/Bcc, Reply-To, custom headers, tags, metadata, inline content, and attachments
- Webhook event names, signature verification, timestamp rules, and retry behavior, if webhooks are offered
- Account permissions, verified-domain requirements, and plan restrictions

Treat an SDK, CLI, framework integration, inbound-mail endpoint, or campaign feature as a separate integration surface unless it documents an outbound transport that Jooosi Mail can use. Do not add a scheme when the provider does not document a customer-facing endpoint for it. Save official setup and API links for the profile metadata and user documentation.

## 2. Add or Update the Profile

Create or edit `src/Mail/Profile/Builtin/{Provider}Profile.php`. Follow the profile contract in [`MailProfileInterface.php`](../src/Mail/Profile/MailProfileInterface.php) and inspect relevant existing profiles for repository conventions. For example, [`MailKiteProfile.php`](../src/Mail/Profile/Builtin/MailKiteProfile.php) shows one way to configure multiple schemes, while [`AhaSendProfile.php`](../src/Mail/Profile/Builtin/AhaSendProfile.php) shows one webhook-enabled profile. Adapt examples to the provider rather than treating them as required templates.

The profile should:

1. Use `#[Service]` and `#[MailProfile(...)]`. Give it a stable lowercase `key`, a clear label and description, the official website and docs URL, and applicable use cases.
2. Return every supported DSN scheme from `getSupportedSchemes()`. Keep this list in sync with the transport factory. Choose an API scheme as the default when that is the profile's normal method.
3. Describe the fields users need in `getConfigurationFields()`. Use `type: 'password'` for credentials so they are stored as profile secrets. Use stable field names; use `visible_when` and `required_when` when fields depend on the selected scheme.
4. Validate required values in `validateConfiguration()` using the shared profile helpers such as `getConfigurationDefaults()`, `extractScalarString()`, and `assertRequiredConfigurationValues()`.
5. Build a DSN in `buildDsn()` for each supported scheme. Return `null` for unsupported or incomplete configurations. URL-encode DSN usernames and passwords with `rawurlencode()` and build query options with `buildQueryString()`.

When changing an existing profile, preserve the profile key and existing field names where possible. If a rename or removed scheme is unavoidable, account for already-saved settings, secrets, and DSN overrides before changing runtime behavior.

`ConnectionDsnResolver` uses a saved DSN override when present, otherwise it calls the profile's `buildDsn()`. It also checks the resulting scheme against the profile's supported schemes.

## 3. Add the Transport Factory and Transport

Put provider transports under `src/Mail/Transport/Bridge/{Provider}/Transport/`.

### Factory

Create `{Provider}TransportFactory.php` with `#[Service]` and `#[TransportFactory]`. Extend Symfony's `AbstractTransportFactory`, dispatch each scheme, and have `getSupportedSchemes()` match the profile's scheme list. Throw `UnsupportedSchemeException` for an unknown scheme. The [`MailKiteTransportFactory.php`](../src/Mail/Transport/Bridge/MailKite/Transport/MailKiteTransportFactory.php) is a reference for a provider that offers both API and SMTP.

### API transport

For an HTTP mail API, create `{Provider}ApiTransport.php` and extend Symfony's `AbstractApiTransport` directly, as in [`LettermintApiTransport.php`](../src/Mail/Transport/Bridge/Lettermint/Transport/LettermintApiTransport.php). Use the injected HTTP client, event dispatcher, and logger. Implement the provider request and response handling in `doSendApi()` and build the payload from the `Email` and `Envelope` objects.

Follow the existing provider bridge structure. Avoid adding another API base class, shared trait, or `providerName` state without a concrete use case. Keep authentication and endpoint behavior explicit in the provider transport.

Map or reject each message feature deliberately. Respect the envelope when determining recipients, preserve sender and reply-to semantics where supported, and handle HTML, text, attachments, inline CID content, tags, metadata, and custom headers according to the provider's documented limits. Throw a typed exception for unsupported input instead of silently dropping it. Wrap provider and HTTP failures in the appropriate Symfony transport exception, and set the transport message ID when the API returns one.

### SMTP transport

For ordinary SMTP submission, create a provider-specific `{Provider}SmtpTransport.php` extending Symfony's `EsmtpTransport`, as in [`MailKiteSmtpTransport.php`](../src/Mail/Transport/Bridge/MailKite/Transport/MailKiteSmtpTransport.php). Set the documented relay host, credentials, and TLS behavior there. Map `+smtp` to STARTTLS and `+smtps` to implicit TLS only when those modes are documented. Use the documented default port for each mode; expose other ports only when the profile needs to let users choose them.

Some providers use fixed SMTP usernames or an API key as the password. Encode those rules in the profile DSN and transport factory, rather than making the user enter redundant settings. If a provider's SMTP relay expects a structured payload instead of a normal MIME message, implement that conversion explicitly and document its restrictions; do not treat it as a generic SMTP relay.

If a transport needs to alter a message, clone it before changing headers or content. Keep message transformations local to that provider transport.

## 4. Add Webhook Support Only When the Provider Offers It

Webhooks are separate from sending. If the provider documents delivery callbacks, update the profile's `supportsWebhooks()` and `getWebhookEvents()` methods, then add a discovered adapter under `src/Webhook/Adapter/` that implements `WebhookAdapterInterface`. Use [`AhaSendWebhookAdapter.php`](../src/Webhook/Adapter/AhaSendWebhookAdapter.php) as a reference.

The adapter should identify the matching connection, verify the documented signature or authentication scheme, describe its verification mode, parse provider event names into the shared normalized event shape, and preserve the original payload. Validate that required webhook secrets are present when webhooks are enabled. Do not claim webhook support based only on a provider mentioning webhooks for another product or API feature.

Profiles, transport factories, and webhook adapters are discovered from their attributes; do not add manual registry wiring unless the discovery contract changes.

## 5. Add Focused Coverage and Check Discovery

Add focused integration coverage alongside the existing provider tests, especially in:

- `tests/Integration/Mail/Connection/ProviderProfileTest.php` and `BuiltinProfileTest.php` for profile fields, validation, and DSNs
- `tests/Integration/Mail/Transport/` for API payloads, response errors, SMTP settings, and provider limitations
- `tests/Integration/Webhook/Adapter/WebhookAdapterTest.php` for webhook verification and event parsing

Cover each supported scheme, required and missing credentials, secrets that contain URL-reserved characters, and boundary cases from provider documentation. Use test doubles or mocked HTTP responses; do not make automated tests depend on live provider accounts or send live email.

Use `wp jooosi-mail connection:profiles` to confirm discovery, supported schemes, configuration fields, and webhook support. Use `wp jooosi-mail webhook:status --all=true` when webhook behavior changes. Run relevant tests and lint changed PHP files; see [DEVELOPMENT.md](DEVELOPMENT.md) for the available validation commands.

## 6. Update User-Facing Documentation

Update the same change set so the shipped profile and its documentation agree:

- `README.md` and `readme.txt`: update both provider catalogs with the implemented sending methods and webhook support. Add terms and privacy links under **External services** in `readme.txt` for named providers.
- [OPERATIONS.md](OPERATIONS.md): update relevant setup requirements and delivery limits.
- `CHANGELOG.md`: record notable provider or transport additions under **Unreleased**.
- `#[MailProfile(...)]`: keep the website, docs URL, description, and use cases accurate.

Use `wp jooosi-mail connection:profiles` as the runtime source of truth for an installed site's profile keys, schemes, fields, and webhook support.

## Review Checklist

- [ ] Official documentation confirms each implemented endpoint, credential, region, and TLS mode.
- [ ] Profile scheme choices, factory dispatch, and factory scheme list agree.
- [ ] Secret fields are stored as secrets, DSNs encode credentials, and validation reports missing configuration clearly.
- [ ] API payload mapping respects the `Email` and `Envelope`; unsupported features fail explicitly.
- [ ] SMTP security, port defaults, fixed usernames, and message adaptations match provider documentation.
- [ ] Webhook claims, signature verification, event names, and profile metadata match official documentation.
- [ ] Focused coverage and discovery checks are updated for the change.
- [ ] Both provider catalogs, operations guide, external service disclosures, and changelog reflect the shipped behavior.

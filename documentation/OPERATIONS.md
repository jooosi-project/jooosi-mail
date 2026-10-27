# Jooosi Mail Operations

This guide covers setup, delivery, and troubleshooting. See the [provider catalog](../README.md#email-providers) for the current source tree; an installed release may have a different catalog. Run `wp jooosi-mail connection:profiles` to inspect its profiles, schemes, fields, and webhook support.

## Setup

1. Activate the plugin and open **Jooosi Mail → Connections** in WordPress admin.
2. Add a connection, select its sending method, and enter the provider credentials and sender settings. Use credentials and a sender authorized by the provider.
3. Set a default connection if needed, then use **Email Logs → Send test**. Check the log and delivery attempts for the result.
4. Choose delivery mode, routing, sender policy, logging, and retry settings under **Settings**. Enable webhooks and alerts if needed.

The plugin requires WordPress 7.0+ and PHP 8.1+. The PHP Sodium extension is needed to encrypt connection and alert credentials using WordPress authentication salts. Preserve those salts when restoring or moving a site, or re-enter affected credentials after changing them.

The admin app is available at `wp-admin/admin.php?page=jooosi-mail`. It includes Dashboard, Connections, Email Logs, Queue Logs, Webhook Event Logs, Settings, and About.

## Sending and Routing

When enabled, Jooosi Mail intercepts `wp_mail()` unless another `pre_wp_mail` filter has already returned a result. **Email Logs** also offers **Compose email**, **Send test**, and **Resend email**. Compose accepts To, Cc, Bcc, Reply-To, rich text, an optional preferred connection, and an optional send time in the browser's local timezone.

A successful submission in async mode means the message was queued, not that the provider delivered it. Check Email Logs and delivery attempts for its final state. Test email uses `wp_mail()`: if interception is disabled or preempted, it may use another mail implementation instead.

Resending a retained log creates a new submission using current routing and delivery settings; it does not change the original. The action requires confirmation and can create duplicate delivery, including when the original is still queued or processing. Stale scheduling and message identity headers are removed from the new submission.

Settings control synchronous or asynchronous delivery and one of four routing strategies:

- `single` selects one available connection per attempt; it does not try another connection after a delivery failure.
- `failover` orders available connections and can try subsequent candidates.
- `weighted_random` (the default) and `round_robin` select a primary using weights, then order remaining candidates for failover. Round robin uses shared, persisted state.

Preferred and default connections influence selection but are not hard locks. Availability, health, priority, weight, circuit breakers, and rate limits also affect routing. Queued mail uses current routing defaults at delivery time. If all connections are temporarily unavailable, queued delivery can be deferred until a route becomes available.

Send from WP-CLI:

```bash
wp jooosi-mail mail:test --to=you@example.com
wp jooosi-mail mail:test --to=you@example.com --connection-id=3
wp jooosi-mail mail:send --to=you@example.com --subject="Hello" --text-message="Hello from WP-CLI."
wp jooosi-mail mail:send --to=you@example.com --subject="Reminder" --text-message="Your reminder." --send-at="2030-01-01T09:00:00+07:00"
```

`mail:send` also accepts `--html-message`, `--cc`, `--bcc`, `--reply-to`, and `--connection-id`. `--send-at` accepts an ISO 8601 timestamp, Unix timestamp in seconds, or a PHP `strtotime` expression such as `tomorrow 9am`. Local and relative times use the WordPress timezone. Scheduled messages use the queue even in sync mode and run on the first queue execution at or after the requested time.

### Provider Setup

- **MailKite:** Use SMTP for inline CID attachments.
- **PostShiba:** The REST API uses a send-only API key and supports an optional cluster ID. SMTP uses a cluster credential with AUTH PLAIN after STARTTLS at `smtp.postshiba.com:587`; implicit TLS is not available. Send from verified domains with a sending-ready cluster. The REST API supports up to 50 recipients, one Reply-To address, one category, and 10 attachments totaling 18 MB; inline CID attachments require SMTP. SMTP messages have a 25 MB limit. Provider-hosted templates are not used; Jooosi Mail sends the composed message. Cluster capacity limits can reject sends when hourly or daily caps are reached, so size capacity for peak traffic. PostShiba recommends SMTP for high-volume sends.
- **PufferPost:** A batch may partially accept recipients before failing, so retrying can resend to already accepted recipients. The integration sends subject and body inline; provider-hosted templates are not supported.
- **TurboSMTP:** Choose the global or EU region.
- **Helo and JetEmail:** SMTP uses separate credentials. Helo's SMTP user is scoped to a Channel; JetEmail selects the global or EU relay by region. Helo's API can use an optional Channel ID.
- **Lettermint, Lettr, Plunk, Sequenzy, and Unosend:** SMTP uses the API key as the password with a provider-specific username. Lettermint can also use an optional route.
- **Iterable and Loops:** Both require a published provider-side transactional template, accept one recipient per request, and do not support Cc, Bcc, Reply-To, tags, or custom headers. Iterable requires a campaign ID, passes message fields as `dataFields` and metadata separately, and does not support attachments. Loops passes message fields and metadata as `dataVariables`; API attachments require provider-side enablement and cannot be inline, while SMTP does not support attachments.
- **Primitive API:** One To recipient; no Cc, Bcc, Reply-To, custom headers, tags, or metadata. **Sequenzy API:** Up to 50 To recipients, one Reply-To address, and metadata variables; no Cc, Bcc, custom headers, or tags. **Lettr API:** Up to 50 recipients across To, Cc, and Bcc.
- **Plunk and Sequenzy APIs:** For inline content, HTML is sent when present; otherwise the plain-text body is used. **SendHeron:** One To recipient; text-only content is converted to escaped HTML.

## Connections and Settings

Inspect and manage connections with WP-CLI:

```bash
wp jooosi-mail connection:profiles
wp jooosi-mail connection:list
wp jooosi-mail connection:status --all
wp jooosi-mail connection:update 3 --weight=5 --priority=10 --rate-limit-hour=500
wp jooosi-mail connection:set-default 3
wp jooosi-mail connection:enable 3
wp jooosi-mail connection:disable 3
```

`connection:create` accepts profile-specific fields; inspect `connection:profiles` and `wp help jooosi-mail connection:create` first. `connection:delete <id>` prompts for confirmation. Settings are stored in the `jooosi_mail_config` option and cover interception, sender and return-path policy, logging, delivery and routing, rate limits, circuit breakers, queue retries, and alerts.

### Failure Alerts

**Settings → Alerts** can notify Telegram, Discord, and Slack when a message reaches terminal failure. A pending retry does not trigger an alert. Each site sends directly to the configured service; credentials are encrypted in `jooosi_mail_config`.

- **Telegram:** Create a bot with [@BotFather](https://t.me/BotFather) and save its token and destination. For a direct message, start the bot and use the numeric chat ID. Private groups use numeric IDs; public supergroups and channels can use `@username`. A channel bot needs permission to post. A regular user's `@username` is not a valid destination.
- **Discord:** Create a webhook in the destination channel and save its URL.
- **Slack:** Install an app with the `chat:write` bot scope, invite the bot to the channel, and save the Bot User OAuth Token and channel ID.

Use **Send test alert** to check the saved configuration. A blank secret keeps the existing credential; **Disconnect** removes it and disables the channel. Alerts contain the site name, subject, error, and mail-log link. The body and recipient list are not attached, but an error may contain recipient information. If retention is disabled, the linked terminal log may already have been removed.

## Queue and Retention

Async mail is stored in plugin tables and processed by Action Scheduler wakeups, recurring scheduled runs, or WP-CLI. Scheduled HTTP processing uses an owner lease so only one worker chain runs at a time. Make sure WordPress scheduled tasks can run; idle or blocked scheduling can delay delivery.

```bash
wp jooosi-mail queue:status
wp jooosi-mail queue:work --limit=25 --time-limit=20
wp jooosi-mail queue:processing --limit=20
wp jooosi-mail queue:release-stale --older-than=300
wp jooosi-mail queue:failed --limit=20
wp jooosi-mail queue:retry --id=42
```

`queue:status` distinguishes ready and deferred pending work, processing, stale claims, and failures. `queue:work` releases stale claims before processing; `--limit` controls the claim batch size, and multiple batches may be processed within `--time-limit`. Retry delays are configurable and can also reflect temporary route unavailability. `queue:retry` without an ID retries failed messages in bulk.

Email logging is enabled and terminal logs are retained forever by default. A retention period can be configured, or retained email logging disabled. Pending, queued, and processing mail keeps its payload because the queue needs it for delivery. When logging is disabled, terminal mail logs and delivery attempts are removed; webhook events are detached from deleted logs. Scheduled cleanup also prunes terminal queue-attempt history according to a configured retention period. Queue messages and webhook events remain separate operational records.

## Webhooks

For a supported profile, enable webhooks on the connection and configure the displayed URL with the provider:

```text
/wp-json/jooosi-mail/v1/webhook/{connection_id}
```

See the [provider catalog](../README.md#email-providers) for profile-level webhook support. The endpoint returns `404` for a connection with webhooks disabled.

Verification varies by adapter and configured credentials: it may use a shared secret, signature, public key, IP allowlist, or accept unsigned callbacks. Some configurations are unsupported until required secrets are supplied. Check the actual connection before relying on webhook feedback:

PostShiba signs each delivery event with HMAC-SHA256 over `{timestamp}.{raw body}`. Save the endpoint's Signing secret in the connection's webhook secret field. It reports processed, deferred, delivered, bounce, dropped, spam-report, and unsubscribe events.

```bash
wp jooosi-mail webhook:status --all=true
wp jooosi-mail webhook:events --limit=20
wp jooosi-mail webhook:events --connection-id=3
```

Events are stored, projected into WordPress hooks, and can affect routing health. Replayed callbacks are not currently deduplicated, so repeated events can apply health feedback more than once.

## Troubleshooting

- **A message stays queued:** inspect `queue:status` and Queue Logs, check Action Scheduler and WordPress scheduling, then use `queue:work` for a manual run. Deferred messages may be waiting for a scheduled time, retry delay, or an available connection.
- **Delivery fails:** inspect Email Logs and `mail:attempts`, then check connection credentials, sender authorization, provider restrictions, and `connection:status --all`.
- **Webhook events are missing:** check the provider's URL and webhook configuration, confirm the connection has webhooks enabled, and inspect `webhook:status --all=true` for its verification state.
- **The plugin fails to boot after an update or move:** inspect `container:status`; `container:clear` removes the compiled cache so it can rebuild on the next boot. Cache identity includes source and runtime context.
- **Schema changes are pending:** inspect `migration:status` and `migration:list`; `migration:run --dry-run` and `migration:rollback --dry-run` show planned changes.

```bash
wp jooosi-mail mail:attempts --limit=20
wp jooosi-mail mail:attempts --mail-log-id=42
wp jooosi-mail container:status
wp jooosi-mail container:clear
wp jooosi-mail migration:status
```

Provider-side quota synchronization, webhook deduplication, and webhook verification for every profile are not currently available. See the [roadmap](../ROADMAP.md) for planned work.

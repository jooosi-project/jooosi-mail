<?php

declare(strict_types=1);

namespace JooosiMail\Cli;

use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionRepository;
use JooosiMail\Webhook\Adapter\WebhookAdapterRegistry;
use JooosiMail\Webhook\Event\WebhookEventRepository;
use ReflectionClass;
use WP_CLI;

use function WP_CLI\Utils\format_items;

/**
 * Inspect Jooosi Mail webhook state and events.
 *
 * ## EXAMPLES
 *
 *     # Show webhook verification posture for enabled connections.
 *     $ wp jooosi-mail webhook:status
 *     id  name              profile   enabled  adapter   verification        secret
 *     3   Mailgun Primary   mailgun   yes      Mailgun   hmac-shared-secret  yes
 *
 *     # List recent webhook events.
 *     $ wp jooosi-mail webhook:events --limit=10
 *     id  connection          mail_log_id  event_type  occurred_at           transport_message_id  provider_event_id
 *     11  #3 Mailgun Primary  42           delivered   2026-03-23 09:22:00   01HR...               evt_123
 *
 * @since 0.1.0
 */
#[Service]
final class WebhookCommand
{
    public function __construct(
        private readonly ConnectionRepository $connectionRepository,
        private readonly WebhookAdapterRegistry $webhookAdapterRegistry,
        private readonly WebhookEventRepository $webhookEventRepository,
    ) {
    }

    /**
     * Show Jooosi Mail webhook status for configured connections.
     *
     * ## OPTIONS
     *
     * [--all=<all>]
     * : Include connections where webhooks are currently disabled.
     * ---
     * default: false
     * options:
     *   - 0
     *   - 1
     *   - false
     *   - true
     * ---
     *
     * ## EXAMPLES
     *
     *     # Inspect enabled webhook connections.
     *     $ wp jooosi-mail webhook:status
     *     id  name              profile   enabled  adapter   verification        secret
     *     3   Mailgun Primary   mailgun   yes      Mailgun   hmac-shared-secret  yes
     *
     *     # Include connections where webhooks are disabled.
     *     $ wp jooosi-mail webhook:status --all=true
     *     id  name              profile    enabled  adapter    verification  secret
     *     3   Mailgun Primary   mailgun    yes      Mailgun    hmac-shared-secret  yes
     *     4   SendGrid Backup   sendgrid   no       SendGrid   disabled      no
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Show Jooosi Mail webhook status for configured connections.')]
    public function status(array $args, array $assocArgs): void
    {
        $includeDisabled = $this->resolveBoolean($assocArgs['all'] ?? false);
        $connections = array_values(array_filter(
            $this->connectionRepository->findAll(),
            static fn (Connection $connection): bool => $includeDisabled || $connection->webhookEnabled,
        ));

        if ($connections === []) {
            WP_CLI::success($includeDisabled ? 'No configured connections found.' : 'No webhook-enabled connections found.');

            return;
        }

        $items = array_map(function (Connection $connection): array {
            $adapter = $this->webhookAdapterRegistry->resolve($connection);

            return [
                'id' => (string) ($connection->id ?? 0),
                'name' => $connection->name,
                'profile' => $connection->profileKey,
                'enabled' => $connection->webhookEnabled ? 'yes' : 'no',
                'adapter' => $this->formatAdapterName($adapter::class),
                'verification' => $connection->webhookEnabled ? $adapter->describeVerification($connection) : 'disabled',
                'secret' => $connection->hasWebhookSecret() ? 'yes' : 'no',
            ];
        }, $connections);

        format_items('table', $items, ['id', 'name', 'profile', 'enabled', 'adapter', 'verification', 'secret']);
    }

    /**
     * List recent Jooosi Mail webhook events.
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Maximum number of webhook events to show.
     * ---
     * default: 20
     * ---
     *
     * [--connection-id=<connection-id>]
     * : Filter events to a specific connection id.
     *
     * [--mail-log-id=<mail-log-id>]
     * : Filter events to a specific mail log id.
     *
     * [--event-type=<event-type>]
     * : Filter events by normalized event type.
     *
     * ## EXAMPLES
     *
     *     # List recent webhook events.
     *     $ wp jooosi-mail webhook:events --limit=10
     *     id  connection          mail_log_id  event_type  occurred_at           transport_message_id  provider_event_id
     *     11  #3 Mailgun Primary  42           delivered   2026-03-23 09:22:00   01HR...               evt_123
     *
     *     # Filter events for a single mail log.
     *     $ wp jooosi-mail webhook:events --mail-log-id=42
     *     id  connection          mail_log_id  event_type  occurred_at           transport_message_id  provider_event_id
     *     11  #3 Mailgun Primary  42           delivered   2026-03-23 09:22:00   01HR...               evt_123
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'List recent Jooosi Mail webhook events.')]
    public function events(array $args, array $assocArgs): void
    {
        $rows = $this->webhookEventRepository->listRecent(
            limit: max(1, (int) ($assocArgs['limit'] ?? 20)),
            connectionId: isset($assocArgs['connection-id']) ? max(1, (int) $assocArgs['connection-id']) : null,
            mailLogId: isset($assocArgs['mail-log-id']) ? max(1, (int) $assocArgs['mail-log-id']) : null,
            eventType: $this->normalizeFilter($assocArgs['event-type'] ?? null),
        );

        if ($rows === []) {
            WP_CLI::success('No webhook events found.');

            return;
        }

        $items = array_map(fn (array $row): array => [
            'id' => (string) (int) ($row['id'] ?? 0),
            'connection' => $this->formatConnectionLabel($row),
            'mail_log_id' => $this->formatOptionalInt($row['mail_log_id'] ?? null),
            'event_type' => (string) ($row['event_type'] ?? ''),
            'occurred_at' => (string) ($row['occurred_at'] ?? $row['created_at'] ?? ''),
            'transport_message_id' => $this->formatValue((string) ($row['transport_message_id'] ?? '')),
            'provider_event_id' => $this->formatValue((string) ($row['provider_event_id'] ?? '')),
        ], $rows);

        format_items('table', $items, ['id', 'connection', 'mail_log_id', 'event_type', 'occurred_at', 'transport_message_id', 'provider_event_id']);
    }

    /**
     * @since 0.1.0
     */
    private function formatAdapterName(string $className): string
    {
        $reflectionClass = new ReflectionClass($className);
        $name = $reflectionClass->getShortName();

        if (str_ends_with($name, 'WebhookAdapter')) {
            $name = substr($name, 0, -14);
        }

        return $name !== '' ? $name : $reflectionClass->getShortName();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @since 0.1.0
     */
    private function formatConnectionLabel(array $row): string
    {
        $connectionId = isset($row['connection_id']) ? (int) $row['connection_id'] : 0;
        $connectionName = trim((string) ($row['connection_name'] ?? ''));

        if ($connectionId > 0 && $connectionName !== '') {
            return sprintf('#%d %s', $connectionId, $connectionName);
        }

        if ($connectionId > 0) {
            return sprintf('#%d', $connectionId);
        }

        return 'n/a';
    }

    /**
     * @since 0.1.0
     */
    private function formatOptionalInt(mixed $value): string
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (string) (int) $value;
        }

        return '';
    }

    /**
     * @since 0.1.0
     */
    private function formatValue(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? $value : '';
    }

    /**
     * @since 0.1.0
     */
    private function normalizeFilter(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @since 0.1.0
     */
    private function resolveBoolean(mixed $value, bool $default = false): bool
    {
        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($resolved) ? $resolved : $default;
    }
}

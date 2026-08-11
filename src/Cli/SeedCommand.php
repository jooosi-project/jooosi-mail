<?php

declare(strict_types=1);

namespace JooosiMail\Cli;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Queue\Message\SendEmailMessage;
use JooosiMail\Queue\Transport\DatabaseTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use WP_CLI;

/**
 * Seed Jooosi Mail demo data for local development.
 *
 * ## EXAMPLES
 *
 *     # Seed 3 dedicated demo connections plus 100 mail logs, webhook events, and queue rows.
 *     $ wp jooosi-mail seed:demo --count=100
 *     Success: Seeded 100 mail logs, 100 webhook events, and 100 queue messages using 3 seed connection(s); created 3 connection row(s).
 *
 * @since 0.1.0
 */
#[Service]
final class SeedCommand
{
    /**
     * @var mixed[]
     */
    private const CONNECTION_NAMES = [
        'Seed Primary Route',
        'Seed Backup Route',
        'Seed Bulk Route',
    ];

    /**
     * @var mixed[]
     */
    private const MAIL_STATUSES = [
        'pending',
        'sent',
        'queued',
        'processing',
        'failed',
    ];

    /**
     * @var mixed[]
     */
    private const WEBHOOK_EVENT_TYPES = [
        'delivered',
        'opened',
        'clicked',
        'processed',
        'bounce',
        'complained',
        'deferred',
        'spam_report',
    ];

    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Seed demo data into Jooosi Mail tables.
     *
     * ## OPTIONS
     *
     * [--count=<count>]
     * : Number of rows to seed for each dataset.
     * ---
     * default: 100
     * ---
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Seed Jooosi Mail demo data for local development.')]
    public function demo(array $args, array $assocArgs): void
    {
        $count = max(1, (int) ($assocArgs['count'] ?? 100));
        $now = time();

        $summary = $this->connection->transactional(function () use ($count, $now): array {
            $seedConnections = $this->resolveSeedConnections($now);
            $connectionIds = $seedConnections['ids'];
            $mailLogIds = $this->seedMailLogs($count, $connectionIds, $now);

            return [
                'connections' => count($connectionIds),
                'created_connections' => $seedConnections['created'],
                'mail_logs' => count($mailLogIds),
                'webhook_events' => $this->seedWebhookEvents($count, $connectionIds, $mailLogIds, $now),
                'queue_messages' => $this->seedQueueMessages($count, $mailLogIds, $now),
            ];
        });

        WP_CLI::success(sprintf(
            'Seeded %d mail logs, %d webhook events, and %d queue messages using %d seed connection(s); created %d connection row(s).',
            $summary['mail_logs'],
            $summary['webhook_events'],
            $summary['queue_messages'],
            $summary['connections'],
            $summary['created_connections'],
        ));
    }

    /**
     * @return array{ids: list<int>, created: int}
     *
     * @since 0.1.0
     */
    private function resolveSeedConnections(int $now): array
    {
        $connectionTable = $this->tableNameResolver->resolve('connections');
        $existingConnections = $this->connection->createQueryBuilder()
            ->select('id', 'name')
            ->from($connectionTable)
            ->where('name IN (:names)')
            ->setParameter('names', array_values(self::CONNECTION_NAMES), ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchAllAssociative();
        $connectionIdsByName = [];

        foreach ($existingConnections as $existingConnection) {
            $name = (string) ($existingConnection['name'] ?? '');

            if ($name === '' || isset($connectionIdsByName[$name])) {
                continue;
            }

            $connectionIdsByName[$name] = (int) ($existingConnection['id'] ?? 0);
        }

        $hasDefaultConnection = (bool) $this->connection->fetchOne(sprintf(
            'SELECT COUNT(*) FROM %s WHERE is_default = 1',
            $connectionTable,
        ));
        $connectionIds = [];
        $createdConnections = 0;

        foreach (self::CONNECTION_NAMES as $index => $connectionName) {
            if (isset($connectionIdsByName[$connectionName]) && $connectionIdsByName[$connectionName] > 0) {
                $connectionIds[] = $connectionIdsByName[$connectionName];
                continue;
            }

            $timestamp = gmdate('Y-m-d H:i:s', $now - (($index + 1) * 300));
            $this->connection->insert($connectionTable, [
                'profile_key' => 'null',
                'name' => $connectionName,
                'dsn' => null,
                'settings_json' => wp_json_encode([
                    'seed' => true,
                    'label' => $connectionName,
                ]),
                'secrets_json' => wp_json_encode([]),
                'is_enabled' => 1,
                'is_default' => ! $hasDefaultConnection && $index === 0 ? 1 : 0,
                'priority' => 10 + ($index * 10),
                'weight' => $index === 0 ? 3 : 1,
                'webhook_enabled' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $connectionIds[] = (int) $this->connection->lastInsertId();
            $createdConnections++;

            if (! $hasDefaultConnection && $index === 0) {
                $hasDefaultConnection = true;
            }
        }

        return [
            'ids' => $connectionIds,
            'created' => $createdConnections,
        ];
    }

    /**
     * @param list<int> $connectionIds
     * @return list<int>
     *
     * @since 0.1.0
     */
    private function seedMailLogs(int $count, array $connectionIds, int $now): array
    {
        $mailLogTable = $this->tableNameResolver->resolve('mail_logs');
        $mailLogIds = [];

        for ($index = 1; $index <= $count; ++$index) {
            $status = self::MAIL_STATUSES[($index - 1) % count(self::MAIL_STATUSES)];
            $createdTimestamp = $now - (($index * 370) % (14 * DAY_IN_SECONDS));
            $createdAt = gmdate('Y-m-d H:i:s', $createdTimestamp);
            $queuedAt = in_array($status, ['queued', 'processing', 'sent', 'failed'], true)
                ? gmdate('Y-m-d H:i:s', $createdTimestamp + MINUTE_IN_SECONDS)
                : null;
            $sentAt = $status === 'sent'
                ? gmdate('Y-m-d H:i:s', $createdTimestamp + (3 * MINUTE_IN_SECONDS))
                : null;
            $connectionId = $status === 'pending'
                ? null
                : $connectionIds[($index - 1) % count($connectionIds)];
            $transportMessageId = in_array($status, ['sent', 'failed'], true)
                ? sprintf('seed-mail-%03d', $index)
                : null;
            $lastError = $status === 'failed'
                ? sprintf('Simulated delivery rejection for seeded mail log %03d.', $index)
                : null;
            $toAddress = sprintf('recipient%03d@example.test', $index);
            $payload = [
                'source' => 'seed',
                'subject' => sprintf('Seed Email %03d', $index),
                'to' => [[
                    'address' => $toAddress,
                    'name' => sprintf('Recipient %03d', $index),
                ]],
                'from' => [[
                    'address' => 'sender@example.test',
                    'name' => 'Seed Sender',
                ]],
                'cc' => $index % 4 === 0 ? [[
                    'address' => sprintf('cc%03d@example.test', $index),
                    'name' => 'Seed CC',
                ]] : [],
                'bcc' => $index % 5 === 0 ? [[
                    'address' => sprintf('bcc%03d@example.test', $index),
                    'name' => 'Seed BCC',
                ]] : [],
                'replyTo' => [[
                    'address' => 'reply@example.test',
                    'name' => 'Reply Desk',
                ]],
                'textBody' => sprintf(
                    'Seeded text body for message %03d. Order #%d. This content exists for search testing.',
                    $index,
                    1000 + $index,
                ),
                'htmlBody' => sprintf(
                    '<p>Seeded HTML body for message <strong>%03d</strong>.</p><p>Order #%d.</p>',
                    $index,
                    1000 + $index,
                ),
            ];
            $plan = [
                'mode' => in_array($status, ['queued', 'processing'], true) ? 'async' : 'sync',
                'connections' => $connectionId !== null ? [$connectionId] : [],
            ];

            $this->connection->insert($mailLogTable, [
                'source' => 'seed',
                'subject' => sprintf('Seed Email %03d', $index),
                'recipients_json' => wp_json_encode([[
                    'address' => $toAddress,
                    'name' => sprintf('Recipient %03d', $index),
                ]]),
                'payload_json' => wp_json_encode($payload),
                'plan_json' => wp_json_encode($plan),
                'status' => $status,
                'final_connection_id' => $connectionId,
                'transport_message_id' => $transportMessageId,
                'last_error' => $lastError,
                'created_at' => $createdAt,
                'queued_at' => $queuedAt,
                'sent_at' => $sentAt,
                'updated_at' => $sentAt ?? $queuedAt ?? $createdAt,
            ]);

            $mailLogIds[] = (int) $this->connection->lastInsertId();
        }

        return $mailLogIds;
    }

    /**
     * @param list<int> $connectionIds
     * @param list<int> $mailLogIds
     *
     * @since 0.1.0
     */
    private function seedWebhookEvents(int $count, array $connectionIds, array $mailLogIds, int $now): int
    {
        $webhookEventTable = $this->tableNameResolver->resolve('webhook_events');

        for ($index = 1; $index <= $count; ++$index) {
            $mailLogId = $mailLogIds[($index - 1) % count($mailLogIds)];
            $connectionId = $connectionIds[($index - 1) % count($connectionIds)];
            $eventType = self::WEBHOOK_EVENT_TYPES[($index - 1) % count(self::WEBHOOK_EVENT_TYPES)];
            $occurredTimestamp = $now - (($index * 211) % (7 * DAY_IN_SECONDS));
            $transportMessageId = sprintf('seed-webhook-%03d', $index);
            $providerEventId = sprintf('seed-webhook-event-%03d', $index);
            $payload = [
                'id' => $providerEventId,
                'event' => $eventType,
                'mail_log_id' => $mailLogId,
                'transport_message_id' => $transportMessageId,
                'provider_event_id' => $providerEventId,
                'meta' => [
                    'batch' => 'seed',
                    'sequence' => $index,
                ],
            ];

            $this->connection->insert($webhookEventTable, [
                'connection_id' => $connectionId,
                'mail_log_id' => $mailLogId,
                'event_type' => $eventType,
                'transport_message_id' => $transportMessageId,
                'provider_event_id' => $providerEventId,
                'payload_json' => wp_json_encode($payload),
                'occurred_at' => gmdate('Y-m-d H:i:s', $occurredTimestamp),
                'created_at' => gmdate('Y-m-d H:i:s', $occurredTimestamp + 30),
            ]);
        }

        return $count;
    }

    /**
     * @param list<int> $mailLogIds
     *
     * @since 0.1.0
     */
    private function seedQueueMessages(int $count, array $mailLogIds, int $now): int
    {
        $queueMessageTable = $this->tableNameResolver->resolve('queue_messages');

        for ($index = 1; $index <= $count; ++$index) {
            $bucket = ($index - 1) % 10;
            $mailLogId = $mailLogIds[($index - 1) % count($mailLogIds)];
            $createdAt = gmdate('Y-m-d H:i:s', $now - (($index * 180) % (10 * DAY_IN_SECONDS)));

            if ($bucket <= 3) {
                $status = 'pending';
                $availableAt = gmdate('Y-m-d H:i:s', $now - ($index * 45));
                $claimedAt = null;
                $claimedBy = null;
                $processedAt = null;
                $lastError = null;
                $attemptCount = 0;
            } elseif ($bucket <= 5) {
                $status = 'pending';
                $availableAt = gmdate('Y-m-d H:i:s', $now + (($index + 1) * 300));
                $claimedAt = null;
                $claimedBy = null;
                $processedAt = null;
                $lastError = null;
                $attemptCount = 0;
            } elseif ($bucket <= 7) {
                $status = 'processing';
                $availableAt = gmdate('Y-m-d H:i:s', $now - HOUR_IN_SECONDS);
                $claimedAt = gmdate('Y-m-d H:i:s', $now - (($index + 1) * 60));
                $claimedBy = wp_generate_uuid4();
                $processedAt = null;
                $lastError = null;
                $attemptCount = 1;
            } else {
                $status = 'failed';
                $availableAt = gmdate('Y-m-d H:i:s', $now - (2 * HOUR_IN_SECONDS));
                $claimedAt = gmdate('Y-m-d H:i:s', $now - (90 * MINUTE_IN_SECONDS));
                $claimedBy = wp_generate_uuid4();
                $processedAt = gmdate('Y-m-d H:i:s', $now - (($index + 1) * 120));
                $lastError = sprintf('Seeded queue failure %03d: provider timeout.', $index);
                $attemptCount = 3;
            }

            $encodedEnvelope = $this->serializer->encode(Envelope::wrap(new SendEmailMessage($mailLogId)));

            $this->connection->insert($queueMessageTable, [
                'body' => $encodedEnvelope['body'],
                'headers_json' => wp_json_encode($encodedEnvelope['headers'] ?? []),
                'queue_name' => DatabaseTransport::NAME,
                'status' => $status,
                'priority' => 10 + ($index % 5),
                'available_at' => $availableAt,
                'claimed_at' => $claimedAt,
                'claimed_by' => $claimedBy,
                'attempt_count' => $attemptCount,
                'max_attempts' => 3,
                'last_error' => $lastError,
                'created_at' => $createdAt,
                'updated_at' => $processedAt ?? $claimedAt ?? $availableAt,
                'processed_at' => $processedAt,
            ]);
        }

        return $count;
    }
}

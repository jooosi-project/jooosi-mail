<?php

declare(strict_types=1);

namespace JooosiMail\Webhook\Event;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;

/**
 * Repository for persisted webhook events.
 *
 * @since 0.1.0
 */
#[Service]
final class WebhookEventRepository
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function save(WebhookEvent $event): int
    {
        $this->connection->insert($this->tableNameResolver->resolve('webhook_events'), [
            'connection_id' => $event->connectionId,
            'mail_log_id' => $event->mailLogId,
            'event_type' => $event->eventType,
            'transport_message_id' => $event->transportMessageId,
            'provider_event_id' => $event->providerEventId,
            'payload_json' => wp_json_encode($event->payload),
            'occurred_at' => $event->occurredAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    public function listRecent(int $limit = 20, ?int $connectionId = null, ?int $mailLogId = null, ?string $eventType = null): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select(
                'e.id',
                'e.connection_id',
                'e.mail_log_id',
                'e.event_type',
                'e.transport_message_id',
                'e.provider_event_id',
                'e.occurred_at',
                'e.created_at',
                'c.name AS connection_name',
            )
            ->from($this->tableNameResolver->resolve('webhook_events'), 'e')
            ->leftJoin('e', $this->tableNameResolver->resolve('connections'), 'c', 'c.id = e.connection_id')
            ->orderBy('e.id', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($connectionId !== null) {
            $queryBuilder
                ->andWhere('e.connection_id = :connection_id')
                ->setParameter('connection_id', $connectionId);
        }

        if ($mailLogId !== null) {
            $queryBuilder
                ->andWhere('e.mail_log_id = :mail_log_id')
                ->setParameter('mail_log_id', $mailLogId);
        }

        if ($eventType !== null && $eventType !== '') {
            $queryBuilder
                ->andWhere('e.event_type = :event_type')
                ->setParameter('event_type', strtolower($eventType));
        }

        return $queryBuilder->fetchAllAssociative();
    }

    /**
     * @param list<int> $connectionIds
     * @return array<int, list<string>>
     *
     * @since 0.1.0
     */
    public function listRecentEventTypesByConnection(array $connectionIds, int $hours = 24, int $sampleSize = 20): array
    {
        if ($connectionIds === []) {
            return [];
        }

        $since = gmdate('Y-m-d H:i:s', time() - ($hours * 3600));
        $rows = $this->connection->createQueryBuilder()
            ->select('connection_id', 'event_type')
            ->from($this->tableNameResolver->resolve('webhook_events'))
            ->where('connection_id IN (:connection_ids)')
            ->andWhere('COALESCE(occurred_at, created_at) >= :since')
            ->orderBy('id', 'DESC')
            ->setMaxResults(max($sampleSize * count($connectionIds), 50))
            ->setParameter('connection_ids', array_values($connectionIds), ArrayParameterType::INTEGER)
            ->setParameter('since', $since)
            ->fetchAllAssociative();

        $eventsByConnection = [];

        foreach ($rows as $row) {
            $connectionId = (int) ($row['connection_id'] ?? 0);

            if ($connectionId <= 0) {
                continue;
            }

            $eventsByConnection[$connectionId] ??= [];

            if (count($eventsByConnection[$connectionId]) >= $sampleSize) {
                continue;
            }

            $eventsByConnection[$connectionId][] = strtolower((string) ($row['event_type'] ?? ''));
        }

        return $eventsByConnection;
    }
}

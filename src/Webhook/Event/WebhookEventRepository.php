<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Event;

use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
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
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver)
    {
    }
    /**
     * @since 0.1.0
     */
    public function save(\JooosiMail\Webhook\Event\WebhookEvent $event): int
    {
        $this->connection->insert($this->tableNameResolver->resolve('webhook_events'), ['connection_id' => $event->connectionId, 'mail_log_id' => $event->mailLogId, 'event_type' => $event->eventType, 'transport_message_id' => $event->transportMessageId, 'provider_event_id' => $event->providerEventId, 'payload_json' => wp_json_encode($event->payload), 'occurred_at' => $event->occurredAt, 'created_at' => gmdate('Y-m-d H:i:s')]);
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
        $queryBuilder->select('e.id', 'e.connection_id', 'e.mail_log_id', 'e.event_type', 'e.transport_message_id', 'e.provider_event_id', 'e.occurred_at', 'e.created_at', 'c.name AS connection_name')->from($this->tableNameResolver->resolve('webhook_events'), 'e')->leftJoin('e', $this->tableNameResolver->resolve('connections'), 'c', 'c.id = e.connection_id')->orderBy('e.id', 'DESC')->setMaxResults(max(1, $limit));
        if ($connectionId !== null) {
            $queryBuilder->andWhere('e.connection_id = :connection_id')->setParameter('connection_id', $connectionId);
        }
        if ($mailLogId !== null) {
            $queryBuilder->andWhere('e.mail_log_id = :mail_log_id')->setParameter('mail_log_id', $mailLogId);
        }
        if ($eventType !== null && $eventType !== '') {
            $queryBuilder->andWhere('e.event_type = :event_type')->setParameter('event_type', strtolower($eventType));
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
        $sampleSize = max(1, $sampleSize);
        $since = gmdate('Y-m-d H:i:s', time() - $hours * 3600);
        $eventsByConnection = [];
        foreach (array_values(array_unique($connectionIds)) as $connectionId) {
            if ($connectionId <= 0) {
                continue;
            }
            $eventTypes = array_map(static fn(array $row): string => strtolower((string) ($row['event_type'] ?? '')), $this->connection->createQueryBuilder()->select('event_type')->from($this->tableNameResolver->resolve('webhook_events'))->where('connection_id = :connection_id')->andWhere('COALESCE(occurred_at, created_at) >= :since')->orderBy('id', 'DESC')->setMaxResults($sampleSize)->setParameter('connection_id', $connectionId)->setParameter('since', $since)->fetchAllAssociative());
            if ($eventTypes !== []) {
                $eventsByConnection[$connectionId] = $eventTypes;
            }
        }
        return $eventsByConnection;
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Query;

use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
/**
 * Read model for database-backed queue messages.
 *
 * @since 0.1.0
 */
#[Service]
final class QueueMessageQuery
{
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver)
    {
    }
    /**
     * @since 0.1.0
     */
    public function countPending(): int
    {
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE status = :status', $this->tableNameResolver->resolve('queue_messages')), ['status' => 'pending']);
    }
    /**
     * @since 0.1.0
     */
    public function countFailed(): int
    {
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE status = :status', $this->tableNameResolver->resolve('queue_messages')), ['status' => 'failed']);
    }
    /**
     * @return array{pending_ready: int, pending_deferred: int, processing: int, stale_processing: int, failed: int}
     *
     * @since 0.1.0
     */
    public function getStatusSnapshot(int $staleAfter = 300): array
    {
        $row = $this->connection->fetchAssociative(sprintf('SELECT
                SUM(CASE WHEN status = :pending_status AND available_at <= :available_at THEN 1 ELSE 0 END) AS pending_ready,
                SUM(CASE WHEN status = :pending_status AND available_at > :available_at THEN 1 ELSE 0 END) AS pending_deferred,
                SUM(CASE WHEN status = :processing_status THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = :processing_status AND claimed_at IS NOT NULL AND claimed_at < :threshold THEN 1 ELSE 0 END) AS stale_processing,
                SUM(CASE WHEN status = :failed_status THEN 1 ELSE 0 END) AS failed
            FROM %s', $this->tableNameResolver->resolve('queue_messages')), ['pending_status' => 'pending', 'processing_status' => 'processing', 'failed_status' => 'failed', 'available_at' => gmdate('Y-m-d H:i:s'), 'threshold' => gmdate('Y-m-d H:i:s', time() - max(1, $staleAfter))]);
        return ['pending_ready' => (int) ($row['pending_ready'] ?? 0), 'pending_deferred' => (int) ($row['pending_deferred'] ?? 0), 'processing' => (int) ($row['processing'] ?? 0), 'stale_processing' => (int) ($row['stale_processing'] ?? 0), 'failed' => (int) ($row['failed'] ?? 0)];
    }
    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    public function listFailed(int $limit = 50): array
    {
        return $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s WHERE status = :status ORDER BY id DESC LIMIT %d', $this->tableNameResolver->resolve('queue_messages'), $limit), ['status' => 'failed']);
    }
    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    public function listProcessing(int $limit = 50): array
    {
        return $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s WHERE status = :status ORDER BY claimed_at ASC, id ASC LIMIT %d', $this->tableNameResolver->resolve('queue_messages'), $limit), ['status' => 'processing']);
    }
}

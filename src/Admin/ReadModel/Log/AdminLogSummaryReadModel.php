<?php

declare (strict_types=1);
namespace JooosiMail\Admin\ReadModel\Log;

use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
/**
 * Reads aggregate log counts shared by admin overview surfaces.
 *
 * @since 1.0.9
 */
#[Service]
final class AdminLogSummaryReadModel
{
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver)
    {
    }
    /**
     * @return array<string, int>
     *
     * @since 1.0.9
     */
    public function mailSummary(): array
    {
        $row = $this->connection->fetchAssociative(sprintf('SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = :pending_status THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = :queued_status THEN 1 ELSE 0 END) AS queued,
                SUM(CASE WHEN status = :processing_status THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = :sent_status THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = :failed_status THEN 1 ELSE 0 END) AS failed
            FROM %s', $this->tableNameResolver->resolve('mail_logs')), ['pending_status' => 'pending', 'queued_status' => 'queued', 'processing_status' => 'processing', 'sent_status' => 'sent', 'failed_status' => 'failed']);
        return ['total' => (int) ($row['total'] ?? 0), 'pending' => (int) ($row['pending'] ?? 0), 'queued' => (int) ($row['queued'] ?? 0), 'processing' => (int) ($row['processing'] ?? 0), 'sent' => (int) ($row['sent'] ?? 0), 'failed' => (int) ($row['failed'] ?? 0)];
    }
    /**
     * @since 1.0.9
     */
    public function webhookEventCount(): int
    {
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $this->tableNameResolver->resolve('webhook_events')));
    }
}

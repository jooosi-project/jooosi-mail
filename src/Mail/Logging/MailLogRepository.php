<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Logging;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Mail\Routing\DeliveryMode;
use JooosiMail\Mail\Routing\DeliveryPlan;
use JooosiMail\Mail\ValueObject\MailRequest;
use Throwable;

/**
 * Repository for mail lifecycle rows.
 *
 * @since 0.1.0
 */
#[Service]
final class MailLogRepository
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function create(MailRequest $mailRequest, DeliveryPlan $deliveryPlan): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $queued = $deliveryPlan->mode === DeliveryMode::Async;

        $this->connection->insert($this->tableNameResolver->resolve('mail_logs'), [
            'source' => $mailRequest->source,
            'subject' => $mailRequest->subject,
            'recipients_json' => wp_json_encode(array_map(static fn ($address): array => $address->toArray(), $mailRequest->to)),
            'payload_json' => wp_json_encode($mailRequest->toArray()),
            'plan_json' => wp_json_encode($deliveryPlan->toArray()),
            'status' => $queued ? 'queued' : 'pending',
            'created_at' => $now,
            'queued_at' => $queued ? $now : null,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @since 0.1.0
     */
    public function find(int $mailLogId): ?array
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE id = :id LIMIT 1', $this->tableNameResolver->resolve('mail_logs')),
            ['id' => $mailLogId],
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @since 0.1.0
     */
    public function updatePayload(int $mailLogId, MailRequest $mailRequest): void
    {
        $this->connection->update($this->tableNameResolver->resolve('mail_logs'), [
            'payload_json' => wp_json_encode($mailRequest->toArray()),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $mailLogId]);
    }

    /**
     * @since 0.1.0
     */
    public function findIdByTransportMessageId(string $transportMessageId, ?int $connectionId = null): ?int
    {
        if ($transportMessageId === '') {
            return null;
        }

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('id')
            ->from($this->tableNameResolver->resolve('mail_logs'))
            ->where('transport_message_id = :transport_message_id')
            ->setParameter('transport_message_id', $transportMessageId)
            ->orderBy('id', 'DESC')
            ->setMaxResults(1);

        if ($connectionId !== null) {
            $queryBuilder
                ->andWhere('final_connection_id = :connection_id')
                ->setParameter('connection_id', $connectionId);
        }

        $mailLogId = $queryBuilder->fetchOne();

        return is_numeric($mailLogId) ? (int) $mailLogId : null;
    }

    /**
     * @since 0.1.0
     */
    public function markProcessing(int $mailLogId): void
    {
        $this->connection->update($this->tableNameResolver->resolve('mail_logs'), [
            'status' => 'processing',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $mailLogId]);
    }

    /**
     * @since 0.1.0
     */
    public function markSent(int $mailLogId, int $connectionId, ?string $transportMessageId = null): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->update($this->tableNameResolver->resolve('mail_logs'), [
            'status' => 'sent',
            'final_connection_id' => $connectionId,
            'transport_message_id' => $transportMessageId,
            'sent_at' => $now,
            'updated_at' => $now,
        ], ['id' => $mailLogId]);
    }

    /**
     * @since 0.1.0
     */
    public function markFailed(int $mailLogId, string $error): void
    {
        $this->connection->update($this->tableNameResolver->resolve('mail_logs'), [
            'status' => 'failed',
            'last_error' => $error,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $mailLogId]);
    }

    /**
     * @since 0.1.0
     */
    public function markDeferred(int $mailLogId, string $error): void
    {
        $this->connection->update($this->tableNameResolver->resolve('mail_logs'), [
            'status' => 'queued',
            'last_error' => $error,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $mailLogId]);
    }

    /**
     * Deletes a mail log and dependent delivery-attempt rows.
     *
     * @since 0.1.0
     */
    public function deleteCascade(int $mailLogId): bool
    {
        if ($mailLogId <= 0) {
            return false;
        }

        $this->connection->beginTransaction();

        try {
            $this->connection->delete($this->tableNameResolver->resolve('mail_attempts'), [
                'mail_log_id' => $mailLogId,
            ]);
            $this->connection->update($this->tableNameResolver->resolve('webhook_events'), [
                'mail_log_id' => null,
            ], [
                'mail_log_id' => $mailLogId,
            ]);
            $deleted = $this->connection->delete($this->tableNameResolver->resolve('mail_logs'), [
                'id' => $mailLogId,
            ]);
            $this->connection->commit();
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }

        return $deleted > 0;
    }

    /**
     * Deletes terminal logs regardless of age.
     *
     * @since 0.1.0
     */
    public function deleteTerminalLogs(int $limit = 500): int
    {
        return $this->deleteTerminalLogsMatching(null, $limit);
    }

    /**
     * Deletes terminal logs older than the configured retention window.
     *
     * @since 0.1.0
     */
    public function deleteTerminalLogsOlderThan(int $retentionDays, int $limit = 500): int
    {
        $threshold = gmdate('Y-m-d H:i:s', time() - (max(1, $retentionDays) * 86400));

        return $this->deleteTerminalLogsMatching($threshold, $limit);
    }

    /**
     * @since 0.1.0
     */
    private function deleteTerminalLogsMatching(?string $olderThan, int $limit): int
    {
        $deleted = 0;

        foreach ($this->findTerminalLogIds($olderThan, $limit) as $mailLogId) {
            if ($this->deleteCascade($mailLogId)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return list<int>
     *
     * @since 0.1.0
     */
    private function findTerminalLogIds(?string $olderThan, int $limit): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('id')
            ->from($this->tableNameResolver->resolve('mail_logs'))
            ->where('status IN (:statuses)')
            ->orderBy('id', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->setParameter('statuses', ['sent', 'failed'], ArrayParameterType::STRING);

        if ($olderThan !== null) {
            $queryBuilder
                ->andWhere('COALESCE(sent_at, updated_at, created_at) < :older_than')
                ->setParameter('older_than', $olderThan);
        }

        return array_values(array_map(
            static fn (mixed $mailLogId): int => (int) $mailLogId,
            $queryBuilder->fetchFirstColumn(),
        ));
    }
}

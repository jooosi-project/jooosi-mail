<?php

declare(strict_types=1);

namespace JooosiMail\Queue\Logging;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Queue\State\QueueClock;

/**
 * Persists the outcome and error details for each queue processing attempt.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueAttemptRepository
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
        private readonly QueueClock $clock,
    ) {
    }

    /**
     * @since 1.0.9
     */
    public function recordStarted(
        int $queueMessageId,
        int $attemptNumber,
        string $claimedBy,
        string $workerId,
    ): void {
        $this->connection->insert($this->table(), [
            'queue_message_id' => $queueMessageId,
            'attempt_number' => $attemptNumber,
            'claimed_by' => $claimedBy,
            'worker_id' => $workerId,
            'outcome' => 'processing',
            'started_at' => $this->clock->now(),
        ]);
    }

    /**
     * @since 1.0.9
     */
    public function finish(
        int $queueMessageId,
        string $claimedBy,
        string $outcome,
        ?string $errorMessage = null,
        ?int $retryDelaySeconds = null,
    ): void {
        $this->connection->update($this->table(), [
            'outcome' => $outcome,
            'error_message' => $this->limitErrorMessage($errorMessage),
            'retry_delay_seconds' => $retryDelaySeconds,
            'finished_at' => $this->clock->now(),
        ], [
            'queue_message_id' => $queueMessageId,
            'claimed_by' => $claimedBy,
            'outcome' => 'processing',
        ]);
    }

    /**
     * Marks unfinished attempts as interrupted after their queue claim has ended.
     *
     * @since 1.0.9
     */
    public function finishOrphaned(): int
    {
        return $this->connection->executeStatement(sprintf(
            'UPDATE %1$s AS attempt
            LEFT JOIN %2$s AS queue ON queue.id = attempt.queue_message_id
            SET attempt.outcome = CASE
                    WHEN queue.status = :queue_completed THEN :completed
                    WHEN queue.status = :queue_failed THEN :failed
                    ELSE :interrupted
                END,
                attempt.error_message = CASE
                    WHEN queue.status = :queue_completed THEN NULL
                    WHEN queue.status = :queue_failed THEN COALESCE(NULLIF(queue.last_error, \'\'), :error_message)
                    ELSE :error_message
                END,
                attempt.finished_at = :finished_at
            WHERE attempt.outcome = :processing
                AND (queue.id IS NULL OR queue.status <> :queue_processing
                    OR queue.claimed_by IS NULL OR queue.claimed_by <> attempt.claimed_by)',
            $this->table(),
            $this->tableNameResolver->resolve('queue_messages'),
        ), [
            'interrupted' => 'interrupted',
            'error_message' => 'The worker stopped before this attempt completed.',
            'finished_at' => $this->clock->now(),
            'processing' => 'processing',
            'queue_processing' => 'processing',
            'queue_completed' => 'completed',
            'queue_failed' => 'failed',
            'completed' => 'completed',
            'failed' => 'failed',
        ]);
    }

    /**
     * @param list<int> $queueMessageIds
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function listForQueueMessages(array $queueMessageIds): array
    {
        if ($queueMessageIds === []) {
            return [];
        }

        return $this->connection->createQueryBuilder()
            ->select(
                'queue_message_id',
                'attempt_number',
                'outcome',
                'worker_id',
                'error_message',
                'retry_delay_seconds',
                'started_at',
                'finished_at',
            )
            ->from($this->table())
            ->where('queue_message_id IN (:queue_message_ids)')
            ->setParameter('queue_message_ids', $queueMessageIds, ArrayParameterType::INTEGER)
            ->orderBy('id', 'ASC')
            ->fetchAllAssociative();
    }

    /**
     * Deletes old attempt history for queue messages that have reached a terminal state.
     *
     * @since 1.0.9
     */
    public function pruneTerminalHistoryOlderThan(int $retentionDays, int $limit = 500): int
    {
        $threshold = $this->clock->before(max(1, $retentionDays) * 86400);
        $expiredIds = $this->connection->createQueryBuilder()
            ->select('attempt.id')
            ->from($this->table(), 'attempt')
            ->leftJoin(
                'attempt',
                $this->tableNameResolver->resolve('queue_messages'),
                'queue',
                'queue.id = attempt.queue_message_id',
            )
            ->where('attempt.finished_at IS NOT NULL')
            ->andWhere('attempt.finished_at < :queue_attempt_threshold')
            ->andWhere('(queue.id IS NULL OR queue.status IN (:queue_terminal_statuses))')
            ->setParameter('queue_attempt_threshold', $threshold)
            ->setParameter('queue_terminal_statuses', ['completed', 'failed'], ArrayParameterType::STRING)
            ->orderBy('attempt.finished_at', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->fetchFirstColumn();

        if ($expiredIds === []) {
            return 0;
        }

        return $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE id IN (:queue_attempt_ids)', $this->table()),
            ['queue_attempt_ids' => array_map('intval', $expiredIds)],
            ['queue_attempt_ids' => ArrayParameterType::INTEGER],
        );
    }

    /**
     * @since 1.0.9
     */
    private function limitErrorMessage(?string $errorMessage): ?string
    {
        if ($errorMessage === null || trim($errorMessage) === '') {
            return null;
        }

        return mb_substr(trim($errorMessage), 0, 4000);
    }

    /**
     * @since 1.0.9
     */
    private function table(): string
    {
        return $this->tableNameResolver->resolve('queue_message_attempts');
    }
}

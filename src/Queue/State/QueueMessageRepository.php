<?php

declare(strict_types=1);

namespace JooosiMail\Queue\State;

use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Queue\Stamp\DatabaseMessageStamp;

/**
 * Persists queue message state transitions and claims.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueMessageRepository
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
        private readonly QueueClock $clock,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function findReady(int $limit = 25): array
    {
        return $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT * FROM %s WHERE status = :status AND available_at <= :available_at ORDER BY priority ASC, id ASC LIMIT %d',
                $this->table(),
                $limit,
            ),
            [
                'status' => 'pending',
                'available_at' => $this->clock->now(),
            ],
        );
    }

    /**
     * Claims a pending row only if another worker has not claimed it first.
     *
     * @since 1.0.9
     */
    public function claim(int $messageId, QueueClaim $claim): bool
    {
        return $this->connection->update($this->table(), [
            'status' => 'processing',
            'claimed_at' => $claim->claimedAt,
            'claimed_by' => $claim->claimedBy,
            'claimed_worker_id' => $claim->workerId,
            'updated_at' => $claim->claimedAt,
        ], [
            'id' => $messageId,
            'status' => 'pending',
        ]) === 1;
    }

    /**
     * Marks an undecodable payload as failed only while this worker owns its claim.
     *
     * @since 1.0.9
     */
    public function markDecodeFailed(int $messageId, string $claimedBy, string $error): bool
    {
        $now = $this->clock->now();

        return $this->connection->update($this->table(), [
            'status' => 'failed',
            'last_error' => $error,
            'processed_at' => $now,
            'claimed_worker_id' => null,
            'updated_at' => $now,
        ], [
            'id' => $messageId,
            'status' => 'processing',
            'claimed_by' => $claimedBy,
        ]) === 1;
    }

    /**
     * Acknowledges a message only while this receiver still owns its claim.
     *
     * @since 1.0.9
     */
    public function ack(DatabaseMessageStamp $stamp): bool
    {
        $now = $this->clock->now();

        return $this->connection->update($this->table(), [
            'status' => 'completed',
            'last_error' => null,
            'processed_at' => $now,
            'claimed_worker_id' => null,
            'updated_at' => $now,
        ], $this->ownedCriteria($stamp)) === 1;
    }

    /**
     * Rejects a message only while this receiver still owns its claim.
     *
     * @since 1.0.9
     */
    public function reject(DatabaseMessageStamp $stamp, ?string $error = null): bool
    {
        $now = $this->clock->now();
        $values = [
            'status' => 'failed',
            'processed_at' => $now,
            'claimed_worker_id' => null,
            'updated_at' => $now,
        ];

        if ($error !== null) {
            $values['last_error'] = $error;
        }

        return $this->connection->update($this->table(), $values, $this->ownedCriteria($stamp)) === 1;
    }

    /**
     * Increments a dispatch attempt only while this receiver still owns its claim.
     *
     * @since 1.0.9
     */
    public function beginAttempt(DatabaseMessageStamp $stamp): ?int
    {
        $attemptCount = $stamp->attemptCount + 1;
        $updated = $this->connection->update($this->table(), [
            'attempt_count' => $attemptCount,
            'updated_at' => $this->clock->now(),
        ], $this->ownedCriteria($stamp));

        return $updated === 1 ? $attemptCount : null;
    }

    /**
     * Returns a claimed message to the pending state for a later retry.
     *
     * @since 1.0.9
     */
    public function reschedule(DatabaseMessageStamp $stamp, string $error, int $delaySeconds): bool
    {
        return $this->connection->update($this->table(), [
            'status' => 'pending',
            'available_at' => $this->clock->after($delaySeconds),
            'claimed_at' => null,
            'claimed_by' => null,
            'claimed_worker_id' => null,
            'last_error' => $error,
            'processed_at' => null,
            'updated_at' => $this->clock->now(),
        ], $this->ownedCriteria($stamp)) === 1;
    }

    /**
     * Releases a claimed message that was never dispatched.
     *
     * @since 1.0.9
     */
    public function release(DatabaseMessageStamp $stamp): bool
    {
        return $this->connection->update($this->table(), [
            'status' => 'pending',
            'claimed_at' => null,
            'claimed_by' => null,
            'claimed_worker_id' => null,
            'updated_at' => $this->clock->now(),
        ], $this->ownedCriteria($stamp)) === 1;
    }

    /**
     * Returns failed messages to the pending state.
     *
     * @since 1.0.9
     */
    public function retryFailed(?int $messageId = null): int
    {
        $criteria = ['status' => 'failed'];

        if ($messageId !== null) {
            $criteria['id'] = $messageId;
        }

        $now = $this->clock->now();

        return $this->connection->update($this->table(), [
            'status' => 'pending',
            'available_at' => $now,
            'claimed_at' => null,
            'claimed_by' => null,
            'claimed_worker_id' => null,
            'attempt_count' => 0,
            'last_error' => null,
            'processed_at' => null,
            'updated_at' => $now,
        ], $criteria);
    }

    /**
     * Releases processing claims that have exceeded their lease.
     *
     * @since 1.0.9
     */
    public function releaseStaleClaims(int $seconds = 300): int
    {
        $seconds = max(1, $seconds);

        return $this->connection->executeStatement(
            sprintf(
                'UPDATE %s SET status = :pending, claimed_at = NULL, claimed_by = NULL, claimed_worker_id = NULL, updated_at = :updated_at WHERE status = :processing AND claimed_at < :threshold',
                $this->table(),
            ),
            [
                'pending' => 'pending',
                'processing' => 'processing',
                'updated_at' => $this->clock->now(),
                'threshold' => $this->clock->before($seconds),
            ],
        );
    }

    /**
     * @return array{id: int, status: string, claimed_by: string}
     *
     * @since 1.0.9
     */
    private function ownedCriteria(DatabaseMessageStamp $stamp): array
    {
        return [
            'id' => $stamp->messageId,
            'status' => 'processing',
            'claimed_by' => $stamp->claimedBy,
        ];
    }

    /**
     * @since 1.0.9
     */
    private function table(): string
    {
        return $this->tableNameResolver->resolve('queue_messages');
    }
}

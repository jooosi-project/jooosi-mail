<?php

declare(strict_types=1);

namespace JooosiMail\Queue\Maintenance;

use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;

/**
 * Performs operational queue maintenance outside the transport receiver.
 *
 * @since 0.1.0
 */
#[Service]
final class QueueMaintenanceService
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function retryFailed(?int $messageId = null): int
    {
        $criteria = ['status' => 'failed'];
        if ($messageId !== null) {
            $criteria['id'] = $messageId;
        }

        return $this->connection->update($this->tableNameResolver->resolve('queue_messages'), [
            'status' => 'pending',
            'available_at' => gmdate('Y-m-d H:i:s'),
            'claimed_at' => null,
            'claimed_by' => null,
            'attempt_count' => 0,
            'last_error' => null,
            'processed_at' => null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], $criteria);
    }

    /**
     * @since 0.1.0
     */
    public function releaseStaleClaims(int $seconds = 300): int
    {
        $seconds = max(1, $seconds);

        return $this->connection->executeStatement(
            sprintf(
                'UPDATE %s SET status = :pending, claimed_at = NULL, claimed_by = NULL, updated_at = :updated_at WHERE status = :processing AND claimed_at < :threshold',
                $this->tableNameResolver->resolve('queue_messages'),
            ),
            [
                'pending' => 'pending',
                'processing' => 'processing',
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'threshold' => gmdate('Y-m-d H:i:s', time() - $seconds),
            ],
        );
    }
}

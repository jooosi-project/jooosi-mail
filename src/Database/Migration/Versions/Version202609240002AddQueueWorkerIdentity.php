<?php

declare(strict_types=1);

namespace JooosiMail\Database\Migration\Versions;

use Doctrine\DBAL\Connection;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Infrastructure\Database\TableNameResolver;

/**
 * Adds the active worker identity to queue message claims.
 *
 * @since 1.0.9
 */
final class Version202609240002AddQueueWorkerIdentity implements MigrationInterface
{
    public function getVersion(): string
    {
        return '202609240002';
    }

    public function getDescription(): string
    {
        return 'Adds the active worker identity to queue message claims.';
    }

    public function up(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf(
            'ALTER TABLE %s ADD COLUMN claimed_worker_id VARCHAR(190) DEFAULT NULL',
            $tableNameResolver->resolve('queue_messages'),
        ));
    }

    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf(
            'ALTER TABLE %s DROP COLUMN claimed_worker_id',
            $tableNameResolver->resolve('queue_messages'),
        ));
    }
}

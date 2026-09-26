<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration\Versions;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Infrastructure\Database\TableNameResolver;
/**
 * Adds worker identity to recorded queue attempts.
 *
 * @since 1.0.9
 */
final class Version202609240003AddQueueAttemptWorkerIdentity implements MigrationInterface
{
    public function getVersion(): string
    {
        return '202609240003';
    }
    public function getDescription(): string
    {
        return 'Adds worker identity to recorded queue attempts.';
    }
    public function up(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf('ALTER TABLE %s ADD COLUMN worker_id VARCHAR(190) DEFAULT NULL AFTER claimed_by', $tableNameResolver->resolve('queue_message_attempts')));
    }
    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf('ALTER TABLE %s DROP COLUMN worker_id', $tableNameResolver->resolve('queue_message_attempts')));
    }
}

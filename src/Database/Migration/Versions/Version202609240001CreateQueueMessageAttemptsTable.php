<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration\Versions;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Infrastructure\Database\TableNameResolver;
/**
 * Creates queue message attempt history storage.
 *
 * @since 1.0.9
 */
final class Version202609240001CreateQueueMessageAttemptsTable implements MigrationInterface
{
    public function getVersion(): string
    {
        return '202609240001';
    }
    public function getDescription(): string
    {
        return 'Creates queue message attempt history storage.';
    }
    public function up(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        global $wpdb;
        $connection->executeStatement(sprintf('CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue_message_id BIGINT UNSIGNED NOT NULL,
                attempt_number INT UNSIGNED NOT NULL,
                claimed_by VARCHAR(100) NOT NULL,
                outcome VARCHAR(32) NOT NULL DEFAULT \'processing\',
                error_message LONGTEXT DEFAULT NULL,
                retry_delay_seconds INT UNSIGNED DEFAULT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME DEFAULT NULL,
                KEY idx_queue_message_attempts (queue_message_id, id),
                KEY idx_queue_attempt_claim (queue_message_id, claimed_by),
                KEY idx_queue_attempt_outcome (outcome, started_at)
            ) %s', $tableNameResolver->resolve('queue_message_attempts'), $wpdb->get_charset_collate()));
    }
    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('queue_message_attempts')));
    }
}

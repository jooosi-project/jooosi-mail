<?php

declare(strict_types=1);

namespace JooosiMail\Database\Migration\Versions;

use Doctrine\DBAL\Connection;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Infrastructure\Database\TableNameResolver;

/**
 * Creates persisted routing state tables for circuit breakers and rate limits.
 *
 * @since 0.1.0
 */
final class Version202603220001CreateRoutingStateTables implements MigrationInterface
{
    public function getVersion(): string
    {
        return '202603220001';
    }

    public function getDescription(): string
    {
        return 'Creates persisted routing state tables for circuit breakers and rate limits.';
    }

    public function up(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $charsetCollation = $this->getCharsetCollation();

        $connection->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                connection_id BIGINT UNSIGNED NOT NULL,
                recent_failure_count INT NOT NULL DEFAULT 0,
                window_started_at DATETIME DEFAULT NULL,
                last_failure_at DATETIME DEFAULT NULL,
                blacklisted_until DATETIME DEFAULT NULL,
                last_error_message LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (connection_id),
                KEY idx_blacklisted_until (blacklisted_until)
            ) %s',
            $tableNameResolver->resolve('connection_circuit_breakers'),
            $charsetCollation,
        ));

        $connection->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                connection_id BIGINT UNSIGNED NOT NULL,
                period_key VARCHAR(32) NOT NULL,
                usage_count INT NOT NULL DEFAULT 0,
                window_started_at DATETIME NOT NULL,
                window_ends_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uk_connection_period (connection_id, period_key),
                KEY idx_window_ends_at (window_ends_at)
            ) %s',
            $tableNameResolver->resolve('connection_rate_limits'),
            $charsetCollation,
        ));
    }

    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('connection_rate_limits')));
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('connection_circuit_breakers')));
    }

    private function getCharsetCollation(): string
    {
        global $wpdb;

        return $wpdb->get_charset_collate();
    }
}

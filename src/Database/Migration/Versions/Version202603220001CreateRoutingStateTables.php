<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration\Versions;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMailDeps\Doctrine\DBAL\Schema\Table;
use JooosiMailDeps\Doctrine\DBAL\Types\Types;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Database\Migration\MigrationSchema;
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
        $circuitBreakers = new Table($tableNameResolver->resolve('connection_circuit_breakers'));
        $circuitBreakers->addColumn('connection_id', Types::BIGINT, ['unsigned' => \true, 'notnull' => \true]);
        $circuitBreakers->addColumn('recent_failure_count', Types::INTEGER, ['notnull' => \true, 'default' => 0]);
        $circuitBreakers->addColumn('window_started_at', Types::DATETIME_MUTABLE, ['notnull' => \false, 'default' => null]);
        $circuitBreakers->addColumn('last_failure_at', Types::DATETIME_MUTABLE, ['notnull' => \false, 'default' => null]);
        $circuitBreakers->addColumn('blacklisted_until', Types::DATETIME_MUTABLE, ['notnull' => \false, 'default' => null]);
        $circuitBreakers->addColumn('last_error_message', Types::TEXT, ['notnull' => \false, 'default' => null]);
        $circuitBreakers->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $circuitBreakers->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $circuitBreakers->setPrimaryKey(['connection_id']);
        $circuitBreakers->addIndex(['blacklisted_until'], 'idx_blacklisted_until');
        MigrationSchema::createTable($connection, $circuitBreakers);
        $rateLimits = new Table($tableNameResolver->resolve('connection_rate_limits'));
        $rateLimits->addColumn('id', Types::BIGINT, ['unsigned' => \true, 'autoincrement' => \true]);
        $rateLimits->addColumn('connection_id', Types::BIGINT, ['unsigned' => \true, 'notnull' => \true]);
        $rateLimits->addColumn('period_key', Types::STRING, ['length' => 32, 'notnull' => \true]);
        $rateLimits->addColumn('usage_count', Types::INTEGER, ['notnull' => \true, 'default' => 0]);
        $rateLimits->addColumn('window_started_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $rateLimits->addColumn('window_ends_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $rateLimits->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $rateLimits->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $rateLimits->setPrimaryKey(['id']);
        $rateLimits->addUniqueIndex(['connection_id', 'period_key'], 'uk_connection_period');
        $rateLimits->addIndex(['window_ends_at'], 'idx_window_ends_at');
        MigrationSchema::createTable($connection, $rateLimits);
    }
    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('connection_rate_limits'));
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('connection_circuit_breakers'));
    }
}

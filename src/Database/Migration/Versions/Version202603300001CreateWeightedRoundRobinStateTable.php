<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration\Versions;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Infrastructure\Database\TableNameResolver;
/**
 * Creates persisted smooth weighted round robin routing state.
 *
 * @since 0.1.0
 */
final class Version202603300001CreateWeightedRoundRobinStateTable implements MigrationInterface
{
    public function getVersion(): string
    {
        return '202603300001';
    }
    public function getDescription(): string
    {
        return 'Creates persisted smooth weighted round robin routing state.';
    }
    public function up(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf('CREATE TABLE IF NOT EXISTS %s (
                scope_key VARCHAR(64) NOT NULL,
                weights_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (scope_key),
                KEY idx_updated_at (updated_at)
            ) %s', $tableNameResolver->resolve('weighted_round_robin_states'), $this->getCharsetCollation()));
    }
    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('weighted_round_robin_states')));
    }
    private function getCharsetCollation(): string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }
}

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
        $table = new Table($tableNameResolver->resolve('weighted_round_robin_states'));
        $table->addColumn('scope_key', Types::STRING, ['length' => 64, 'notnull' => \true]);
        $table->addColumn('weights_json', Types::TEXT, ['notnull' => \true]);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $table->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $table->setPrimaryKey(['scope_key']);
        $table->addIndex(['updated_at'], 'idx_updated_at');
        MigrationSchema::createTable($connection, $table);
    }
    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('weighted_round_robin_states'));
    }
}

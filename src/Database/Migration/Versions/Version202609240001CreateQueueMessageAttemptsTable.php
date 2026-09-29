<?php

declare(strict_types=1);

namespace JooosiMail\Database\Migration\Versions;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Database\Migration\MigrationSchema;
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
        $table = new Table($tableNameResolver->resolve('queue_message_attempts'));
        $table->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $table->addColumn('queue_message_id', Types::BIGINT, ['unsigned' => true, 'notnull' => true]);
        $table->addColumn('attempt_number', Types::INTEGER, ['unsigned' => true, 'notnull' => true]);
        $table->addColumn('claimed_by', Types::STRING, ['length' => 100, 'notnull' => true]);
        $table->addColumn('outcome', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'processing']);
        $table->addColumn('error_message', Types::TEXT, ['notnull' => false, 'default' => null]);
        $table->addColumn('retry_delay_seconds', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => null]);
        $table->addColumn('started_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $table->addColumn('finished_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['queue_message_id', 'id'], 'idx_queue_message_attempts');
        $table->addIndex(['queue_message_id', 'claimed_by'], 'idx_queue_attempt_claim');
        $table->addIndex(['outcome', 'started_at'], 'idx_queue_attempt_outcome');
        MigrationSchema::createTable($connection, $table);
    }

    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('queue_message_attempts'));
    }
}

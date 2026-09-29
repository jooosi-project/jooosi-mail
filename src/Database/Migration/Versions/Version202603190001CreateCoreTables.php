<?php

declare(strict_types=1);

namespace JooosiMail\Database\Migration\Versions;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Database\Migration\MigrationSchema;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Queue\Transport\DatabaseTransport;

/**
 * Creates Jooosi Mail core persistence tables.
 *
 * @since 0.1.0
 */
final class Version202603190001CreateCoreTables implements MigrationInterface
{
    public function getVersion(): string
    {
        return '202603190001';
    }

    public function getDescription(): string
    {
        return 'Creates Jooosi Mail core persistence tables.';
    }

    public function up(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connections = new Table($tableNameResolver->resolve('connections'));
        $connections->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $connections->addColumn('profile_key', Types::STRING, ['length' => 100, 'notnull' => true]);
        $connections->addColumn('name', Types::STRING, ['length' => 190, 'notnull' => true]);
        $connections->addColumn('dsn', Types::TEXT, ['notnull' => false, 'default' => null]);
        $connections->addColumn('settings_json', Types::TEXT, ['notnull' => false, 'default' => null]);
        $connections->addColumn('secrets_json', Types::TEXT, ['notnull' => false, 'default' => null]);
        $connections->addColumn('is_enabled', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
        $connections->addColumn('is_default', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $connections->addColumn('priority', Types::INTEGER, ['notnull' => true, 'default' => 10]);
        $connections->addColumn('weight', Types::INTEGER, ['notnull' => true, 'default' => 1]);
        $connections->addColumn('webhook_enabled', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $connections->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $connections->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $connections->setPrimaryKey(['id']);
        $connections->addIndex(['profile_key'], 'idx_profile_key');
        $connections->addIndex(['is_enabled', 'is_default'], 'idx_enabled_default');
        $connections->addIndex(['priority'], 'idx_priority');
        MigrationSchema::createTable($connection, $connections);

        $queueMessages = new Table($tableNameResolver->resolve('queue_messages'));
        $queueMessages->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $queueMessages->addColumn('body', Types::TEXT, ['notnull' => true]);
        $queueMessages->addColumn('headers_json', Types::TEXT, ['notnull' => false, 'default' => null]);
        $queueMessages->addColumn('queue_name', Types::STRING, ['length' => 190, 'notnull' => true, 'default' => DatabaseTransport::NAME]);
        $queueMessages->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'pending']);
        $queueMessages->addColumn('priority', Types::SMALLINT, ['notnull' => true, 'default' => 10]);
        $queueMessages->addColumn('available_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $queueMessages->addColumn('claimed_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
        $queueMessages->addColumn('claimed_by', Types::STRING, ['length' => 100, 'notnull' => false, 'default' => null]);
        $queueMessages->addColumn('attempt_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $queueMessages->addColumn('max_attempts', Types::INTEGER, ['notnull' => true, 'default' => 3]);
        $queueMessages->addColumn('last_error', Types::TEXT, ['notnull' => false, 'default' => null]);
        $queueMessages->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $queueMessages->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $queueMessages->addColumn('processed_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
        $queueMessages->setPrimaryKey(['id']);
        $queueMessages->addIndex(['status', 'available_at', 'priority'], 'idx_queue_lookup');
        $queueMessages->addIndex(['claimed_at'], 'idx_claimed_at');
        $queueMessages->addIndex(['processed_at'], 'idx_processed_at');
        MigrationSchema::createTable($connection, $queueMessages);

        $mailLogs = new Table($tableNameResolver->resolve('mail_logs'));
        $mailLogs->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $mailLogs->addColumn('source', Types::STRING, ['length' => 100, 'notnull' => true]);
        $mailLogs->addColumn('subject', Types::TEXT, ['notnull' => true]);
        $mailLogs->addColumn('recipients_json', Types::TEXT, ['notnull' => true]);
        $mailLogs->addColumn('payload_json', Types::TEXT, ['notnull' => true]);
        $mailLogs->addColumn('plan_json', Types::TEXT, ['notnull' => true]);
        $mailLogs->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'pending']);
        $mailLogs->addColumn('final_connection_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false, 'default' => null]);
        $mailLogs->addColumn('transport_message_id', Types::STRING, ['length' => 190, 'notnull' => false, 'default' => null]);
        $mailLogs->addColumn('last_error', Types::TEXT, ['notnull' => false, 'default' => null]);
        $mailLogs->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $mailLogs->addColumn('queued_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
        $mailLogs->addColumn('sent_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
        $mailLogs->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $mailLogs->setPrimaryKey(['id']);
        $mailLogs->addIndex(['status', 'created_at'], 'idx_status_created');
        $mailLogs->addIndex(['transport_message_id'], 'idx_transport_message_id');
        MigrationSchema::createTable($connection, $mailLogs);

        $mailAttempts = new Table($tableNameResolver->resolve('mail_attempts'));
        $mailAttempts->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $mailAttempts->addColumn('mail_log_id', Types::BIGINT, ['unsigned' => true, 'notnull' => true]);
        $mailAttempts->addColumn('connection_id', Types::BIGINT, ['unsigned' => true, 'notnull' => true]);
        $mailAttempts->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true]);
        $mailAttempts->addColumn('error_message', Types::TEXT, ['notnull' => false, 'default' => null]);
        $mailAttempts->addColumn('debug_output', Types::TEXT, ['notnull' => false, 'default' => null]);
        $mailAttempts->addColumn('transport_message_id', Types::STRING, ['length' => 190, 'notnull' => false, 'default' => null]);
        $mailAttempts->addColumn('started_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $mailAttempts->addColumn('finished_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
        $mailAttempts->setPrimaryKey(['id']);
        $mailAttempts->addIndex(['mail_log_id'], 'idx_mail_log_id');
        $mailAttempts->addIndex(['connection_id'], 'idx_connection_id');
        $mailAttempts->addIndex(['status'], 'idx_status');
        MigrationSchema::createTable($connection, $mailAttempts);

        $webhookEvents = new Table($tableNameResolver->resolve('webhook_events'));
        $webhookEvents->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $webhookEvents->addColumn('connection_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false, 'default' => null]);
        $webhookEvents->addColumn('mail_log_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false, 'default' => null]);
        $webhookEvents->addColumn('event_type', Types::STRING, ['length' => 100, 'notnull' => true]);
        $webhookEvents->addColumn('transport_message_id', Types::STRING, ['length' => 190, 'notnull' => false, 'default' => null]);
        $webhookEvents->addColumn('provider_event_id', Types::STRING, ['length' => 190, 'notnull' => false, 'default' => null]);
        $webhookEvents->addColumn('payload_json', Types::TEXT, ['notnull' => true]);
        $webhookEvents->addColumn('occurred_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
        $webhookEvents->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        $webhookEvents->setPrimaryKey(['id']);
        $webhookEvents->addIndex(['connection_id'], 'idx_connection_id');
        $webhookEvents->addIndex(['mail_log_id'], 'idx_mail_log_id');
        $webhookEvents->addIndex(['event_type'], 'idx_event_type');
        $webhookEvents->addIndex(['transport_message_id'], 'idx_transport_message_id');
        $webhookEvents->addIndex(['provider_event_id'], 'idx_provider_event_id');
        MigrationSchema::createTable($connection, $webhookEvents);
    }

    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('webhook_events'));
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('mail_attempts'));
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('mail_logs'));
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('queue_messages'));
        MigrationSchema::dropTable($connection, $tableNameResolver->resolve('connections'));
    }
}

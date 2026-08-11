<?php

declare(strict_types=1);

namespace JooosiMail\Database\Migration\Versions;

use Doctrine\DBAL\Connection;
use JooosiMail\Database\Migration\MigrationInterface;
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
        $charsetCollation = $this->getCharsetCollation();

        $connection->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                profile_key VARCHAR(100) NOT NULL,
                name VARCHAR(190) NOT NULL,
                dsn TEXT DEFAULT NULL,
                settings_json LONGTEXT DEFAULT NULL,
                secrets_json LONGTEXT DEFAULT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                priority INT NOT NULL DEFAULT 10,
                weight INT NOT NULL DEFAULT 1,
                webhook_enabled TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_profile_key (profile_key),
                KEY idx_enabled_default (is_enabled, is_default),
                KEY idx_priority (priority)
            ) %s',
            $tableNameResolver->resolve('connections'),
            $charsetCollation,
        ));

        $connection->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                body LONGTEXT NOT NULL,
                headers_json LONGTEXT DEFAULT NULL,
                queue_name VARCHAR(190) NOT NULL DEFAULT \'%s\',
                status VARCHAR(32) NOT NULL DEFAULT \'pending\',
                priority SMALLINT NOT NULL DEFAULT 10,
                available_at DATETIME NOT NULL,
                claimed_at DATETIME DEFAULT NULL,
                claimed_by VARCHAR(100) DEFAULT NULL,
                attempt_count INT NOT NULL DEFAULT 0,
                max_attempts INT NOT NULL DEFAULT 3,
                last_error LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                processed_at DATETIME DEFAULT NULL,
                KEY idx_queue_lookup (status, available_at, priority),
                KEY idx_claimed_at (claimed_at),
                KEY idx_processed_at (processed_at)
            ) %s',
            $tableNameResolver->resolve('queue_messages'),
            DatabaseTransport::NAME,
            $charsetCollation,
        ));

        $connection->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(100) NOT NULL,
                subject TEXT NOT NULL,
                recipients_json LONGTEXT NOT NULL,
                payload_json LONGTEXT NOT NULL,
                plan_json LONGTEXT NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'pending\',
                final_connection_id BIGINT UNSIGNED DEFAULT NULL,
                transport_message_id VARCHAR(190) DEFAULT NULL,
                last_error LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                queued_at DATETIME DEFAULT NULL,
                sent_at DATETIME DEFAULT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_status_created (status, created_at),
                KEY idx_transport_message_id (transport_message_id)
            ) %s',
            $tableNameResolver->resolve('mail_logs'),
            $charsetCollation,
        ));

        $connection->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                mail_log_id BIGINT UNSIGNED NOT NULL,
                connection_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(32) NOT NULL,
                error_message LONGTEXT DEFAULT NULL,
                debug_output LONGTEXT DEFAULT NULL,
                transport_message_id VARCHAR(190) DEFAULT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME DEFAULT NULL,
                KEY idx_mail_log_id (mail_log_id),
                KEY idx_connection_id (connection_id),
                KEY idx_status (status)
            ) %s',
            $tableNameResolver->resolve('mail_attempts'),
            $charsetCollation,
        ));

        $connection->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                connection_id BIGINT UNSIGNED DEFAULT NULL,
                mail_log_id BIGINT UNSIGNED DEFAULT NULL,
                event_type VARCHAR(100) NOT NULL,
                transport_message_id VARCHAR(190) DEFAULT NULL,
                provider_event_id VARCHAR(190) DEFAULT NULL,
                payload_json LONGTEXT NOT NULL,
                occurred_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_connection_id (connection_id),
                KEY idx_mail_log_id (mail_log_id),
                KEY idx_event_type (event_type),
                KEY idx_transport_message_id (transport_message_id),
                KEY idx_provider_event_id (provider_event_id)
            ) %s',
            $tableNameResolver->resolve('webhook_events'),
            $charsetCollation,
        ));
    }

    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('webhook_events')));
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('mail_attempts')));
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('mail_logs')));
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('queue_messages')));
        $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $tableNameResolver->resolve('connections')));
    }

    private function getCharsetCollation(): string
    {
        global $wpdb;

        return $wpdb->get_charset_collate();
    }
}

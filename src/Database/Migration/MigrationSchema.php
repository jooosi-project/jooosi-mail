<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMailDeps\Doctrine\DBAL\Platforms\SQLitePlatform;
use JooosiMailDeps\Doctrine\DBAL\Schema\Table;
/**
 * Creates migration tables through the active DBAL platform.
 *
 * @since 0.1.0
 */
final class MigrationSchema
{
    /**
     * Create a table when it does not already exist.
     *
     * @since 0.1.0
     */
    public static function createTable(Connection $connection, Table $table): void
    {
        $schemaManager = $connection->createSchemaManager();
        if ($schemaManager->tablesExist([$table->getName()])) {
            return;
        }
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::makeSqliteIndexNamesUnique($table);
        } else {
            self::applyWordPressCharset($table);
        }
        $schemaManager->createTable($table);
    }
    /**
     * Drop a table when it exists.
     *
     * @since 0.1.0
     */
    public static function dropTable(Connection $connection, string $tableName): void
    {
        $schemaManager = $connection->createSchemaManager();
        if ($schemaManager->tablesExist([$tableName])) {
            $schemaManager->dropTable($tableName);
        }
    }
    /**
     * Preserve the charset and collation selected by WordPress on MySQL.
     *
     * @since 0.1.0
     */
    private static function applyWordPressCharset(Table $table): void
    {
        global $wpdb;
        if (!isset($wpdb) || !method_exists($wpdb, 'get_charset_collate')) {
            return;
        }
        $charsetCollation = $wpdb->get_charset_collate();
        if (preg_match('/CHARACTER SET\s+([a-zA-Z0-9_]+)/i', $charsetCollation, $charsetMatch) === 1) {
            $table->addOption('charset', $charsetMatch[1]);
        }
        if (preg_match('/COLLATE\s+([a-zA-Z0-9_]+)/i', $charsetCollation, $collationMatch) === 1) {
            $table->addOption('collation', $collationMatch[1]);
        }
    }
    /**
     * SQLite index names are unique across the database, unlike MySQL index names.
     *
     * @since 0.1.0
     */
    private static function makeSqliteIndexNamesUnique(Table $table): void
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }
            $newName = 'idx_' . substr(hash('sha256', $table->getName() . ':' . $index->getName()), 0, 32);
            $table->renameIndex($index->getName(), $newName);
        }
    }
}

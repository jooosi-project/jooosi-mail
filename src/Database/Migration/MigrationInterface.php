<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMail\Infrastructure\Database\TableNameResolver;
/**
 * Contract for Jooosi Mail schema migrations.
 *
 * @since 0.1.0
 */
interface MigrationInterface
{
    /**
     * @since 0.1.0
     */
    public function getVersion(): string;
    /**
     * @since 0.1.0
     */
    public function getDescription(): string;
    /**
     * @since 0.1.0
     */
    public function up(Connection $connection, TableNameResolver $tableNameResolver): void;
    /**
     * @since 0.1.0
     */
    public function down(Connection $connection, TableNameResolver $tableNameResolver): void;
}

<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Schema;

use JooosiMailDeps\Doctrine\DBAL\Connection;
/**
 * Creates a schema manager for the given connection.
 *
 * This interface is an extension point for applications that need to override schema managers.
 */
interface SchemaManagerFactory
{
    public function createSchemaManager(Connection $connection): AbstractSchemaManager;
}

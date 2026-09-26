<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Schema;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMailDeps\Doctrine\DBAL\Exception;
/**
 * A schema manager factory that returns the default schema manager for the given platform.
 */
final class DefaultSchemaManagerFactory implements SchemaManagerFactory
{
    /** @throws Exception If the platform does not support creating schema managers yet. */
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return $connection->getDatabasePlatform()->createSchemaManager($connection);
    }
}

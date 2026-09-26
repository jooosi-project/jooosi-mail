<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware;

use JooosiMailDeps\Doctrine\DBAL\Driver;
use JooosiMailDeps\Doctrine\DBAL\Driver\Connection;
use JooosiMailDeps\Doctrine\DBAL\Driver\Middleware;
use JooosiMailDeps\Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;
final class EnableForeignKeys implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware
        {
            /**
             * {@inheritDoc}
             */
            public function connect(
                #[SensitiveParameter]
                array $params
            ): Connection
            {
                $connection = parent::connect($params);
                $connection->exec('PRAGMA foreign_keys=ON');
                return $connection;
            }
        };
    }
}

<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver;

use JooosiMailDeps\Doctrine\DBAL\Driver;
use JooosiMailDeps\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use JooosiMailDeps\Doctrine\DBAL\Driver\API\SQLite\ExceptionConverter;
use JooosiMailDeps\Doctrine\DBAL\Platforms\SQLitePlatform;
use JooosiMailDeps\Doctrine\DBAL\ServerVersionProvider;
/**
 * Abstract base implementation of the {@see Driver} interface for SQLite based drivers.
 */
abstract class AbstractSQLiteDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): SQLitePlatform
    {
        return new SQLitePlatform();
    }
    public function getExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}

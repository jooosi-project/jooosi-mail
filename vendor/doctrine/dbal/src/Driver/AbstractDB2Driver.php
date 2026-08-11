<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver;

use JooosiMailDeps\Doctrine\DBAL\Driver;
use JooosiMailDeps\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use JooosiMailDeps\Doctrine\DBAL\Driver\API\IBMDB2\ExceptionConverter;
use JooosiMailDeps\Doctrine\DBAL\Platforms\DB2Platform;
use JooosiMailDeps\Doctrine\DBAL\ServerVersionProvider;
/**
 * Abstract base implementation of the {@see Driver} interface for IBM DB2 based drivers.
 */
abstract class AbstractDB2Driver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): DB2Platform
    {
        return new DB2Platform();
    }
    public function getExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}

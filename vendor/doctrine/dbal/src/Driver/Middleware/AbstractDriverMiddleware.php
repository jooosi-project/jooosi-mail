<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\Middleware;

use JooosiMailDeps\Doctrine\DBAL\Driver;
use JooosiMailDeps\Doctrine\DBAL\Driver\API\ExceptionConverter;
use JooosiMailDeps\Doctrine\DBAL\Driver\Connection as DriverConnection;
use JooosiMailDeps\Doctrine\DBAL\Platforms\AbstractPlatform;
use JooosiMailDeps\Doctrine\DBAL\ServerVersionProvider;
use SensitiveParameter;
abstract class AbstractDriverMiddleware implements Driver
{
    public function __construct(private readonly Driver $wrappedDriver)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[SensitiveParameter]
        array $params
    ): DriverConnection
    {
        return $this->wrappedDriver->connect($params);
    }
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        return $this->wrappedDriver->getDatabasePlatform($versionProvider);
    }
    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->wrappedDriver->getExceptionConverter();
    }
}

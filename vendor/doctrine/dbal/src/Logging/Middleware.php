<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Logging;

use JooosiMailDeps\Doctrine\DBAL\Driver as DriverInterface;
use JooosiMailDeps\Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
final class Middleware implements MiddlewareInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }
    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new Driver($driver, $this->logger);
    }
}

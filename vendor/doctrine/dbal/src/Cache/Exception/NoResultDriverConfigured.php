<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Cache\Exception;

use JooosiMailDeps\Doctrine\DBAL\Cache\CacheException;
final class NoResultDriverConfigured extends CacheException
{
    public static function new(): self
    {
        return new self('Trying to cache a query but no result driver is configured.');
    }
}

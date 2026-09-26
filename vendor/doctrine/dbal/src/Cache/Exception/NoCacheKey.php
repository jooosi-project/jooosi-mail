<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Cache\Exception;

use JooosiMailDeps\Doctrine\DBAL\Cache\CacheException;
final class NoCacheKey extends CacheException
{
    public static function new(): self
    {
        return new self('No cache key was set.');
    }
}

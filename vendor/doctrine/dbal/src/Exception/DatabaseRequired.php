<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Exception;

use JooosiMailDeps\Doctrine\DBAL\Exception;
use function sprintf;
class DatabaseRequired extends \Exception implements Exception
{
    public static function new(string $methodName): self
    {
        return new self(sprintf('A database is required for the method: %s.', $methodName));
    }
}

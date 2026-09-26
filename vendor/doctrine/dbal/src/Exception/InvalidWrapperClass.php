<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Exception;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use function sprintf;
final class InvalidWrapperClass extends InvalidArgumentException
{
    public static function new(string $wrapperClass): self
    {
        return new self(sprintf('The given wrapper class %s has to be a subtype of %s.', $wrapperClass, Connection::class));
    }
}

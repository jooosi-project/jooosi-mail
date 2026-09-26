<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Schema\Exception;

use JooosiMailDeps\Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;
use function sprintf;
final class UnknownColumnOption extends InvalidArgumentException implements SchemaException
{
    public static function new(string $name): self
    {
        return new self(sprintf('The "%s" column option is not supported.', $name));
    }
}

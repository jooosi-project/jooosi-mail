<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Exception\InvalidColumnType;

use JooosiMailDeps\Doctrine\DBAL\Exception\InvalidColumnType;
use JooosiMailDeps\Doctrine\DBAL\Platforms\AbstractPlatform;
use function get_debug_type;
use function sprintf;
final class ColumnValuesRequired extends InvalidColumnType
{
    /**
     * @param AbstractPlatform $platform The target platform
     * @param string           $type     The SQL column type
     */
    public static function new(AbstractPlatform $platform, string $type): self
    {
        return new self(sprintf('%s requires the values of a %s column to be specified', get_debug_type($platform), $type));
    }
}

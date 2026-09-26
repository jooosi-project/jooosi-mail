<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Types;

use JooosiMailDeps\Doctrine\DBAL\ParameterType;
use JooosiMailDeps\Doctrine\DBAL\Platforms\AbstractPlatform;
use JooosiMailDeps\Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use function is_resource;
use function is_string;
use function stream_get_contents;
/**
 * Type that maps ab SQL BINARY/VARBINARY to a PHP resource stream.
 */
class BinaryType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBinaryTypeDeclarationSQL($column);
    }
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        if (!is_string($value)) {
            throw ValueNotConvertible::new($value, Types::BINARY);
        }
        return $value;
    }
    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }
}

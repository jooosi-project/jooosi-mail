<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Types;

use JooosiMailDeps\Doctrine\DBAL\ParameterType;
use JooosiMailDeps\Doctrine\DBAL\Platforms\AbstractPlatform;
final class AsciiStringType extends StringType
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getAsciiStringTypeDeclarationSQL($column);
    }
    public function getBindingType(): ParameterType
    {
        return ParameterType::ASCII;
    }
}

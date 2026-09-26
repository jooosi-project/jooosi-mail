<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Types;

use JooosiMailDeps\Doctrine\DBAL\Platforms\AbstractPlatform;
/**
 * Type that maps an SQL VARCHAR to a PHP string.
 */
class StringType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }
}

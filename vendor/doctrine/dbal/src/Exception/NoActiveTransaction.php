<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Exception;

use JooosiMailDeps\Doctrine\DBAL\ConnectionException;
final class NoActiveTransaction extends ConnectionException
{
    public static function new(): self
    {
        return new self('There is no active transaction.');
    }
}

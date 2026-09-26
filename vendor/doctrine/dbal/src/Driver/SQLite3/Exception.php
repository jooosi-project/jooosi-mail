<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\SQLite3;

use JooosiMailDeps\Doctrine\DBAL\Driver\AbstractException;
/** @internal */
final class Exception extends AbstractException
{
    public static function new(\Exception $exception): self
    {
        return new self($exception->getMessage(), null, (int) $exception->getCode(), $exception);
    }
}

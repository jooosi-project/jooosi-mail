<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\Mysqli\Exception;

use JooosiMailDeps\Doctrine\DBAL\Driver\AbstractException;
use function sprintf;
/** @internal */
final class FailedReadingStreamOffset extends AbstractException
{
    public static function new(int $parameter): self
    {
        return new self(sprintf('Failed reading the stream resource for parameter #%d.', $parameter));
    }
}

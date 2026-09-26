<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Exception;

use JooosiMailDeps\Doctrine\DBAL\Exception;
use function sprintf;
/** @internal */
final class NoKeyValue extends \Exception implements Exception
{
    public static function fromColumnCount(int $columnCount): self
    {
        return new self(sprintf('Fetching as key-value pairs requires the result to contain at least 2 columns, %d given.', $columnCount));
    }
}

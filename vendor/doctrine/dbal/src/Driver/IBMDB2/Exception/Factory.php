<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\IBMDB2\Exception;

use JooosiMailDeps\Doctrine\DBAL\Driver\AbstractException;
use function preg_match;
/** @internal */
final class Factory
{
    /**
     * @param callable(int): T $constructor
     *
     * @return T
     *
     * @template T of AbstractException
     */
    public static function create(string $message, callable $constructor): AbstractException
    {
        $code = 0;
        if (preg_match('/ SQL(\d+)N /', $message, $matches) === 1) {
            $code = -(int) $matches[1];
        }
        return $constructor($code);
    }
}

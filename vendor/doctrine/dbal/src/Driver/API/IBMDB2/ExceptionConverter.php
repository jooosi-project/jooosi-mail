<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\API\IBMDB2;

use JooosiMailDeps\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use JooosiMailDeps\Doctrine\DBAL\Driver\Exception;
use JooosiMailDeps\Doctrine\DBAL\Exception\ConnectionException;
use JooosiMailDeps\Doctrine\DBAL\Exception\DriverException;
use JooosiMailDeps\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use JooosiMailDeps\Doctrine\DBAL\Exception\InvalidFieldNameException;
use JooosiMailDeps\Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use JooosiMailDeps\Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use JooosiMailDeps\Doctrine\DBAL\Exception\SyntaxErrorException;
use JooosiMailDeps\Doctrine\DBAL\Exception\TableExistsException;
use JooosiMailDeps\Doctrine\DBAL\Exception\TableNotFoundException;
use JooosiMailDeps\Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use JooosiMailDeps\Doctrine\DBAL\Query;
/**
 * @internal
 *
 * @link https://www.ibm.com/docs/en/db2/11.5?topic=messages-sql
 */
final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        return match ($exception->getCode()) {
            -104 => new SyntaxErrorException($exception, $query),
            -203 => new NonUniqueFieldNameException($exception, $query),
            -204 => new TableNotFoundException($exception, $query),
            -206 => new InvalidFieldNameException($exception, $query),
            -407 => new NotNullConstraintViolationException($exception, $query),
            -530, -531, -532, -20356 => new ForeignKeyConstraintViolationException($exception, $query),
            -601 => new TableExistsException($exception, $query),
            -803 => new UniqueConstraintViolationException($exception, $query),
            -1336, -30082 => new ConnectionException($exception, $query),
            default => new DriverException($exception, $query),
        };
    }
}

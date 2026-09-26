<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\API\SQLite;

use JooosiMailDeps\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use JooosiMailDeps\Doctrine\DBAL\Driver\Exception;
use JooosiMailDeps\Doctrine\DBAL\Exception\ConnectionException;
use JooosiMailDeps\Doctrine\DBAL\Exception\DriverException;
use JooosiMailDeps\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use JooosiMailDeps\Doctrine\DBAL\Exception\InvalidFieldNameException;
use JooosiMailDeps\Doctrine\DBAL\Exception\LockWaitTimeoutException;
use JooosiMailDeps\Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use JooosiMailDeps\Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use JooosiMailDeps\Doctrine\DBAL\Exception\ReadOnlyException;
use JooosiMailDeps\Doctrine\DBAL\Exception\SyntaxErrorException;
use JooosiMailDeps\Doctrine\DBAL\Exception\TableExistsException;
use JooosiMailDeps\Doctrine\DBAL\Exception\TableNotFoundException;
use JooosiMailDeps\Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use JooosiMailDeps\Doctrine\DBAL\Query;
use function str_contains;
/** @internal */
final class ExceptionConverter implements ExceptionConverterInterface
{
    /** @link http://www.sqlite.org/c3ref/c_abort.html */
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        if (str_contains($exception->getMessage(), 'database is locked')) {
            return new LockWaitTimeoutException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'must be unique') || str_contains($exception->getMessage(), 'is not unique') || str_contains($exception->getMessage(), 'are not unique') || str_contains($exception->getMessage(), 'UNIQUE constraint failed')) {
            return new UniqueConstraintViolationException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'may not be NULL') || str_contains($exception->getMessage(), 'NOT NULL constraint failed')) {
            return new NotNullConstraintViolationException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'no such table:')) {
            return new TableNotFoundException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'already exists')) {
            return new TableExistsException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'has no column named')) {
            return new InvalidFieldNameException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'ambiguous column name')) {
            return new NonUniqueFieldNameException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'syntax error')) {
            return new SyntaxErrorException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'attempt to write a readonly database')) {
            return new ReadOnlyException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'unable to open database file')) {
            return new ConnectionException($exception, $query);
        }
        if (str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed')) {
            return new ForeignKeyConstraintViolationException($exception, $query);
        }
        return new DriverException($exception, $query);
    }
}

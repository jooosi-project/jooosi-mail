<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\API\SQLSrv;

use JooosiMailDeps\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use JooosiMailDeps\Doctrine\DBAL\Driver\Exception;
use JooosiMailDeps\Doctrine\DBAL\Exception\ConnectionException;
use JooosiMailDeps\Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
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
 * @link https://docs.microsoft.com/en-us/sql/relational-databases/errors-events/database-engine-events-and-errors
 */
final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        return match ($exception->getCode()) {
            102 => new SyntaxErrorException($exception, $query),
            207 => new InvalidFieldNameException($exception, $query),
            208 => new TableNotFoundException($exception, $query),
            209 => new NonUniqueFieldNameException($exception, $query),
            515 => new NotNullConstraintViolationException($exception, $query),
            547, 4712 => new ForeignKeyConstraintViolationException($exception, $query),
            2601, 2627 => new UniqueConstraintViolationException($exception, $query),
            2714 => new TableExistsException($exception, $query),
            3701, 15151 => new DatabaseObjectNotFoundException($exception, $query),
            11001, 18456 => new ConnectionException($exception, $query),
            default => new DriverException($exception, $query),
        };
    }
}

<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\Middleware;

use JooosiMailDeps\Doctrine\DBAL\Driver\Connection;
use JooosiMailDeps\Doctrine\DBAL\Driver\Result;
use JooosiMailDeps\Doctrine\DBAL\Driver\Statement;
abstract class AbstractConnectionMiddleware implements Connection
{
    public function __construct(private readonly Connection $wrappedConnection)
    {
    }
    public function prepare(string $sql): Statement
    {
        return $this->wrappedConnection->prepare($sql);
    }
    public function query(string $sql): Result
    {
        return $this->wrappedConnection->query($sql);
    }
    public function quote(string $value): string
    {
        return $this->wrappedConnection->quote($value);
    }
    public function exec(string $sql): int|string
    {
        return $this->wrappedConnection->exec($sql);
    }
    public function lastInsertId(): int|string
    {
        return $this->wrappedConnection->lastInsertId();
    }
    public function beginTransaction(): void
    {
        $this->wrappedConnection->beginTransaction();
    }
    public function commit(): void
    {
        $this->wrappedConnection->commit();
    }
    public function rollBack(): void
    {
        $this->wrappedConnection->rollBack();
    }
    public function getServerVersion(): string
    {
        return $this->wrappedConnection->getServerVersion();
    }
    /**
     * {@inheritDoc}
     */
    public function getNativeConnection()
    {
        return $this->wrappedConnection->getNativeConnection();
    }
}

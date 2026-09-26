<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\PDO\SQLSrv;

use JooosiMailDeps\Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use JooosiMailDeps\Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use PDO;
final class Connection extends AbstractConnectionMiddleware
{
    public function __construct(private readonly PDOConnection $connection)
    {
        parent::__construct($connection);
    }
    public function prepare(string $sql): Statement
    {
        return new Statement($this->connection->prepare($sql));
    }
    public function getNativeConnection(): PDO
    {
        return $this->connection->getNativeConnection();
    }
}

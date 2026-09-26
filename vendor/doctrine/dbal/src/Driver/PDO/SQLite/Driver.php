<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver\PDO\SQLite;

use JooosiMailDeps\Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use JooosiMailDeps\Doctrine\DBAL\Driver\PDO\Connection;
use JooosiMailDeps\Doctrine\DBAL\Driver\PDO\Exception;
use JooosiMailDeps\Doctrine\DBAL\Driver\PDO\Exception\InvalidConfiguration;
use JooosiMailDeps\Doctrine\DBAL\Driver\PDO\PDOConnect;
use PDOException;
use SensitiveParameter;
use function array_intersect_key;
use function is_string;
final class Driver extends AbstractSQLiteDriver
{
    use PDOConnect;
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[SensitiveParameter]
        array $params
    ): Connection
    {
        foreach (['user', 'password'] as $key) {
            if (isset($params[$key]) && !is_string($params[$key])) {
                throw InvalidConfiguration::notAStringOrNull($key, $params[$key]);
            }
        }
        try {
            $pdo = $this->doConnect($this->constructPdoDsn(array_intersect_key($params, ['path' => \true, 'memory' => \true])), $params['user'] ?? '', $params['password'] ?? '', $params['driverOptions'] ?? []);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
        return new Connection($pdo);
    }
    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @param array<string, mixed> $params
     */
    private function constructPdoDsn(array $params): string
    {
        $dsn = 'sqlite:';
        if (isset($params['path'])) {
            $dsn .= $params['path'];
        } elseif (isset($params['memory'])) {
            $dsn .= ':memory:';
        }
        return $dsn;
    }
}

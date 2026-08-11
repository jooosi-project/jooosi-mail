<?php

declare (strict_types=1);
namespace JooosiMail\Infrastructure\Database;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMailDeps\Doctrine\DBAL\DriverManager;
/**
 * Creates a Doctrine DBAL connection from WordPress config.
 *
 * @since 0.1.0
 */
final class DatabaseConnectionFactory
{
    /**
     * Build the database connection.
     *
     * @since 0.1.0
     */
    public function create(): Connection
    {
        [$host, $port, $socket] = $this->parseHost();
        return DriverManager::getConnection(['dbname' => \DB_NAME, 'user' => \DB_USER, 'password' => \DB_PASSWORD, 'host' => $host, 'port' => $port, 'unix_socket' => $socket, 'charset' => defined('DB_CHARSET') ? \DB_CHARSET : 'utf8mb4', 'driver' => 'mysqli']);
    }
    /**
     * @return array{0: string, 1: int|null, 2: string|null}
     *
     * @since 0.1.0
     */
    private function parseHost(): array
    {
        $host = \DB_HOST;
        $port = null;
        $socket = null;
        if (str_contains($host, ':')) {
            $parts = explode(':', $host, 2);
            $host = $parts[0];
            $suffix = $parts[1] ?? '';
            if ($suffix !== '' && is_numeric($suffix)) {
                $port = (int) $suffix;
            } elseif ($suffix !== '') {
                $socket = $suffix;
            }
        }
        return [$host, $port, $socket];
    }
}

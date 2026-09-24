<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

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
        [$host, $port, $socket] = $this->parseHost(DB_HOST);

        return DriverManager::getConnection([
            'dbname' => DB_NAME,
            'user' => DB_USER,
            'password' => DB_PASSWORD,
            'host' => $host,
            'port' => $port,
            'unix_socket' => $socket,
            'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
            'driver' => 'mysqli',
        ]);
    }

    /**
     * @return array{0: string, 1: int|null, 2: string|null}
     *
     * @since 0.1.0
     */
    private function parseHost(string $host): array
    {
        global $wpdb;

        $parsedHost = $wpdb->parse_db_host($host);

        if ($parsedHost === false) {
            return [$host, null, null];
        }

        [$host, $port, $socket, $isIpv6] = $parsedHost;

        if ($isIpv6 && extension_loaded('mysqlnd')) {
            $host = '[' . $host . ']';
        }

        return [$host, $port, $socket];
    }
}

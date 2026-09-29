<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PDO;
use RuntimeException;

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
        if ($this->usesSqlite()) {
            return DriverManager::getConnection([
                'driver' => 'pdo_sqlite',
                'path' => $this->sqliteDatabasePath(),
                'driverOptions' => [PDO::ATTR_TIMEOUT => 5],
            ]);
        }

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
     * Detect whether WordPress is using SQLite.
     *
     * @since 0.1.0
     */
    private function usesSqlite(): bool
    {
        foreach (['DB_ENGINE', 'DATABASE_ENGINE', 'DATABASE_TYPE'] as $constant) {
            if (defined($constant) && strtolower((string) constant($constant)) === 'sqlite') {
                return true;
            }
        }

        global $wpdb;

        if (! isset($wpdb) || ! method_exists($wpdb, 'get_driver')) {
            return false;
        }

        $driver = $wpdb->get_driver();

        return is_object($driver) && method_exists($driver, 'get_sqlite_pdo');
    }

    /**
     * Resolve the file used by the active WordPress SQLite connection.
     *
     * @since 0.1.0
     */
    private function sqliteDatabasePath(): string
    {
        global $wpdb;

        if (isset($wpdb) && method_exists($wpdb, 'get_driver')) {
            $driver = $wpdb->get_driver();

            if (is_object($driver) && method_exists($driver, 'get_sqlite_pdo')) {
                $pdo = $driver->get_sqlite_pdo();

                if ($pdo instanceof PDO) {
                    $statement = $pdo->query('PRAGMA database_list');
                    $databases = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($databases as $database) {
                        if (
                            ($database['name'] ?? null) === 'main'
                            && is_string($database['file'] ?? null)
                            && $database['file'] !== ''
                        ) {
                            return $database['file'];
                        }
                    }
                }
            }
        }

        if (defined('FQDB') && is_string(FQDB) && FQDB !== '') {
            $path = FQDB;
        } else {
            $directory = defined('DB_DIR')
                ? rtrim((string) DB_DIR, '/\\')
                : rtrim((string) WP_CONTENT_DIR, '/\\') . '/database';
            $file = defined('DB_FILE') ? (string) DB_FILE : '.ht.sqlite';
            $path = $directory . '/' . ltrim($file, '/\\');
        }

        if (! is_file($path)) {
            throw new RuntimeException('Unable to locate the SQLite database file used by WordPress.');
        }

        return $path;
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

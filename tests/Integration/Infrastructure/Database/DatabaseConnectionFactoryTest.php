<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Infrastructure\Database;

use JooosiMail\Infrastructure\Database\DatabaseConnectionFactory;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Covers WordPress database host formats passed to Doctrine's mysqli driver.
 *
 * @since 1.0.8
 */
final class DatabaseConnectionFactoryTest extends WP_UnitTestCase
{
    /**
     * @dataProvider hostProvider
     *
     * @param array{0: string, 1: int|null, 2: string|null} $expected
     *
     * @since 1.0.8
     */
    public function testParsesWordPressDatabaseHostFormats(string $host, array $expected): void
    {
        $method = new ReflectionMethod(DatabaseConnectionFactory::class, 'parseHost');

        self::assertSame($expected, $method->invoke(new DatabaseConnectionFactory(), $host));
    }

    /**
     * @return array<string, array{0: string, 1: array{0: string, 1: int|null, 2: string|null}}>
     *
     * @since 1.0.8
     */
    public static function hostProvider(): array
    {
        $ipv6Host = extension_loaded('mysqlnd') ? '[::1]' : '::1';

        return [
            'hostname' => ['db.example.test', ['db.example.test', null, null]],
            'hostname with port' => ['db.example.test:3307', ['db.example.test', 3307, null]],
            'IPv4 with port' => ['127.0.0.1:3307', ['127.0.0.1', 3307, null]],
            'Unix socket' => ['localhost:/var/run/mysql.sock', ['localhost', null, '/var/run/mysql.sock']],
            'port and Unix socket' => ['localhost:3307:/var/run/mysql.sock', ['localhost', 3307, '/var/run/mysql.sock']],
            'IPv6' => ['::1', [$ipv6Host, null, null]],
            'bracketed IPv6' => ['[::1]', [$ipv6Host, null, null]],
            'IPv6 with port' => ['[::1]:3307', [$ipv6Host, 3307, null]],
            'IPv6 with port and socket' => ['[::1]:3307:/var/run/mysql.sock', [$ipv6Host, 3307, '/var/run/mysql.sock']],
            'unparsed host fallback' => ['not::valid', ['not::valid', null, null]],
        ];
    }

    /**
     * @since 1.0.8
     */
    public function testCreatesLazyConnectionWithWordPressDatabaseParameters(): void
    {
        global $wpdb;

        [$host, $port, $socket, $isIpv6] = $wpdb->parse_db_host(DB_HOST);
        $connection = (new DatabaseConnectionFactory())->create();
        $parameters = $connection->getParams();

        self::assertFalse($connection->isConnected());
        self::assertSame($isIpv6 && extension_loaded('mysqlnd') ? '[' . $host . ']' : $host, $parameters['host']);
        self::assertSame($port, $parameters['port']);
        self::assertSame($socket, $parameters['unix_socket']);
        self::assertSame('mysqli', $parameters['driver']);
    }
}

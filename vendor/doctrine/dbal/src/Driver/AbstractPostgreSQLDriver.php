<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Driver;

use JooosiMailDeps\Doctrine\DBAL\Driver;
use JooosiMailDeps\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use JooosiMailDeps\Doctrine\DBAL\Driver\API\PostgreSQL\ExceptionConverter;
use JooosiMailDeps\Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use JooosiMailDeps\Doctrine\DBAL\Platforms\PostgreSQL120Platform;
use JooosiMailDeps\Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use JooosiMailDeps\Doctrine\DBAL\ServerVersionProvider;
use JooosiMailDeps\Doctrine\Deprecations\Deprecation;
use function preg_match;
use function version_compare;
/**
 * Abstract base implementation of the {@see Driver} interface for PostgreSQL based drivers.
 */
abstract class AbstractPostgreSQLDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): PostgreSQLPlatform
    {
        $version = $versionProvider->getServerVersion();
        if (preg_match('/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/', $version, $versionParts) !== 1) {
            throw InvalidPlatformVersion::new($version, '<major_version>.<minor_version>.<patch_version>');
        }
        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? 0;
        $version = $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
        if (version_compare($version, '12.0', '>=')) {
            return new PostgreSQL120Platform();
        }
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6495', 'Support for Postgres < 12 is deprecated and will be removed in DBAL 5');
        return new PostgreSQLPlatform();
    }
    public function getExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}

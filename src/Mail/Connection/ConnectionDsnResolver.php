<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;
use JooosiMail\Mail\Profile\ProfileRegistry;

/**
 * Resolves the effective DSN for a connection at delivery time.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionDsnResolver
{
    public function __construct(
        private readonly ProfileRegistry $profileRegistry,
        private readonly ProfileMetadataResolver $profileMetadataResolver,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function resolve(Connection $connection): string
    {
        $profile = $this->profileRegistry->get($connection->profileKey);

        if (! $profile instanceof MailProfileInterface) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Profile "%s" is not registered.', $connection->profileKey));
        }

        $dsn = $connection->dsn;

        if ($dsn === null || $dsn === '') {
            $dsn = $profile->buildDsn($connection);
        }

        if ($dsn === null || $dsn === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Connection "%s" could not resolve a DSN.', $connection->name));
        }

        $scheme = $this->extractDsnScheme($dsn);

        if ($scheme === null) {
            throw new ConnectionConfigurationException('The DSN scheme could not be detected.');
        }

        if (! in_array($scheme, $profile->getSupportedSchemes(), true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Profile "%s" does not support DSN scheme "%s".', $this->profileMetadataResolver->getKey($profile), $scheme));
        }

        return $dsn;
    }

    /**
     * @since 0.1.0
     */
    private function extractDsnScheme(string $dsn): ?string
    {
        if (preg_match('/^([a-z0-9+._-]+):\/\//i', $dsn, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }
}

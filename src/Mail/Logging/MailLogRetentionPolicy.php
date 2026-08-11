<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Logging;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\WordPress\OptionStore;

/**
 * Resolves email log retention settings.
 *
 * @since 0.1.0
 */
#[Service]
final class MailLogRetentionPolicy
{
    /**
     * @var string
     */
    public const ENABLED_PATH = 'settings.logging.email.enabled';

    /**
     * @var string
     */
    public const RETENTION_DAYS_PATH = 'settings.logging.email.retention_days';

    public function __construct(
        private readonly OptionStore $optionStore,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function isEmailLoggingEnabled(): bool
    {
        return (bool) $this->optionStore->get(self::ENABLED_PATH, true);
    }

    /**
     * Returns null when terminal email logs should be kept forever.
     *
     * @since 0.1.0
     */
    public function getRetentionDays(): ?int
    {
        $value = $this->optionStore->get(self::RETENTION_DAYS_PATH);

        if ($value === null || $value === '' || $value === 'forever') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $days = (int) $value;

        return $days > 0 ? $days : null;
    }
}

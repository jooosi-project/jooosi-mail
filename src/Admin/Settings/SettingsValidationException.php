<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Settings;

use InvalidArgumentException;

/**
 * Reports a settings validation failure without coupling application code to REST.
 *
 * @since 1.0.9
 */
final class SettingsValidationException extends InvalidArgumentException
{
    /**
     * @since 1.0.9
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}

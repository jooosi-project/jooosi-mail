<?php

declare(strict_types=1);

namespace JooosiMail\Mail\ValueObject;

/**
 * Delivery outcome for a mail send attempt.
 *
 * @since 0.1.0
 */
final class DeliveryResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?int $connectionId = null,
        public readonly ?string $transportMessageId = null,
        public readonly ?string $debug = null,
        public readonly ?string $error = null,
        public readonly bool $temporaryFailure = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {
    }
}

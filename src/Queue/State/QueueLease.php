<?php

declare(strict_types=1);

namespace JooosiMail\Queue\State;

/**
 * Identifies an owner-token-aware queue option lease.
 *
 * @since 1.0.9
 */
final class QueueLease
{
    public function __construct(
        public readonly string $optionName,
        public readonly string $ownerToken,
        public readonly int $expiresAt,
    ) {
    }
}

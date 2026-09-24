<?php

declare(strict_types=1);

namespace JooosiMail\Queue\State;

/**
 * Identifies a queue claim, its worker process, and its timestamp.
 *
 * @since 1.0.9
 */
final class QueueClaim
{
    public function __construct(
        public readonly string $claimedBy,
        public readonly string $claimedAt,
        public readonly string $workerId,
    ) {
    }

    /**
     * @since 1.0.9
     */
    public static function create(string $claimedAt, string $workerId): self
    {
        return new self(
            claimedBy: wp_generate_uuid4(),
            claimedAt: $claimedAt,
            workerId: $workerId,
        );
    }
}

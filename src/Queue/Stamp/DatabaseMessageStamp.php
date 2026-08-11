<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Stamp;

use JooosiMailDeps\Symfony\Component\Messenger\Stamp\StampInterface;
/**
 * Transport metadata for a claimed queue message.
 *
 * @since 0.1.0
 */
final class DatabaseMessageStamp implements StampInterface
{
    public function __construct(public readonly int $messageId, public readonly int $attemptCount, public readonly int $maxAttempts, public readonly string $queueName, public readonly string $claimedBy)
    {
    }
}

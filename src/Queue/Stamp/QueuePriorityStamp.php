<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Stamp;

use JooosiMailDeps\Symfony\Component\Messenger\Stamp\StampInterface;
/**
 * Carries queue priority into the database transport.
 *
 * @since 0.1.0
 */
final class QueuePriorityStamp implements StampInterface
{
    public function __construct(public readonly int $priority)
    {
    }
}

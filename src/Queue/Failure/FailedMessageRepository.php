<?php

declare(strict_types=1);

namespace JooosiMail\Queue\Failure;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Query\QueueMessageQuery;

/**
 * Read model for failed queue messages.
 *
 * @since 0.1.0
 */
#[Service]
final class FailedMessageRepository
{
    public function __construct(
        private readonly QueueMessageQuery $queueMessageQuery,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    public function list(int $limit = 50): array
    {
        return $this->queueMessageQuery->listFailed($limit);
    }

    /**
     * @since 0.1.0
     */
    public function count(): int
    {
        return $this->queueMessageQuery->countFailed();
    }
}

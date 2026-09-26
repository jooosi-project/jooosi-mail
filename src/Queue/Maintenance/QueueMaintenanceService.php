<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Maintenance;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Logging\QueueAttemptRepository;
use JooosiMail\Queue\State\QueueMessageRepository;
/**
 * Performs operational queue maintenance outside the transport receiver.
 *
 * @since 0.1.0
 */
#[Service]
final class QueueMaintenanceService
{
    public function __construct(private readonly QueueMessageRepository $queueMessageRepository, private readonly QueueAttemptRepository $queueAttemptRepository)
    {
    }
    /**
     * @since 0.1.0
     */
    public function retryFailed(?int $messageId = null): int
    {
        return $this->queueMessageRepository->retryFailed($messageId);
    }
    /**
     * @since 0.1.0
     */
    public function releaseStaleClaims(int $seconds = 300): int
    {
        $released = $this->queueMessageRepository->releaseStaleClaims($seconds);
        $this->queueAttemptRepository->finishOrphaned();
        return $released;
    }
}

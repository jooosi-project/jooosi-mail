<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Failure;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Maintenance\QueueMaintenanceService;
/**
 * Retry orchestration for failed queue rows.
 *
 * @since 0.1.0
 */
#[Service]
final class FailedMessageService
{
    public function __construct(private readonly QueueMaintenanceService $queueMaintenanceService)
    {
    }
    /**
     * @since 0.1.0
     */
    public function retry(?int $messageId = null): int
    {
        return $this->queueMaintenanceService->retryFailed($messageId);
    }
}

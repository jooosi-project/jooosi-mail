<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Logging;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Routing\DeliveryPlan;
use JooosiMail\Mail\ValueObject\MailRequest;

/**
 * High-level logging facade for mail lifecycle entries.
 *
 * @since 0.1.0
 */
#[Service]
final class MailLifecycleLogger
{
    public function __construct(
        private readonly MailLogRepository $mailLogRepository,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function create(MailRequest $mailRequest, DeliveryPlan $deliveryPlan): int
    {
        return $this->mailLogRepository->create($mailRequest, $deliveryPlan);
    }
}

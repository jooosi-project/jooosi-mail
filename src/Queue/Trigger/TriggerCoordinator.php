<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Trigger;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Coordinates Action Scheduler queue wakeups.
 *
 * @since 0.1.0
 */
#[Service]
final class TriggerCoordinator
{
    public function __construct(private readonly \JooosiMail\Queue\Trigger\ActionSchedulerTrigger $actionSchedulerTrigger)
    {
    }
    /**
     * @since 0.1.0
     */
    public function trigger(): void
    {
        $this->actionSchedulerTrigger->trigger();
    }
}

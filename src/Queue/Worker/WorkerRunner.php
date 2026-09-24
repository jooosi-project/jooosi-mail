<?php

declare(strict_types=1);

namespace JooosiMail\Queue\Worker;

use JooosiMail\Discovery\Attribute\Hook;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Query\QueueMessageQuery;
use JooosiMail\Queue\State\QueueLease;
use JooosiMail\Queue\State\QueueLeaseService;
use JooosiMail\Queue\Trigger\ActionSchedulerTrigger;

/**
 * Entry points for scheduled and direct worker execution.
 *
 * @since 0.1.0
 */
#[Service]
final class WorkerRunner
{
    /**
     * @var string
     */
    public const RUNNER_LEASE_OPTION = 'jooosi_mail_queue_runner_lease';

    public function __construct(
        private readonly QueueWorker $queueWorker,
        private readonly QueueMessageQuery $queueMessageQuery,
        private readonly ActionSchedulerTrigger $actionSchedulerTrigger,
        private readonly QueueLeaseService $queueLeaseService,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function runNow(int $limit = 25, int $timeLimit = 20): int
    {
        return $this->queueWorker->run($limit, $timeLimit);
    }

    /**
     * @since 0.1.0
     */
    #[Hook(name: ActionSchedulerTrigger::RUN_HOOK, kind: 'action', acceptedArgs: 0)]
    #[Hook(name: ActionSchedulerTrigger::RECURRING_HOOK, kind: 'action', acceptedArgs: 0)]
    public function runScheduled(int $limit = 25, int $timeLimit = 20): int
    {
        $lease = $this->queueLeaseService->acquire(
            self::RUNNER_LEASE_OPTION,
            max(30, $timeLimit + 15),
        );

        if (! $lease instanceof QueueLease) {
            return 0;
        }

        try {
            $processed = $this->queueWorker->run($limit, $timeLimit);
            $snapshot = $this->queueMessageQuery->getStatusSnapshot();

            if ($snapshot['pending_ready'] > 0) {
                $this->actionSchedulerTrigger->trigger();
            }

            return $processed;
        } finally {
            $this->queueLeaseService->release($lease);
        }
    }
}

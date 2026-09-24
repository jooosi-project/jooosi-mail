<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Submission;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Queue\Trigger\TriggerCoordinator;
use Throwable;

/**
 * Runs best-effort queue wakeups and extension notifications after commit.
 *
 * A committed queue message is already accepted. Failures in these observers
 * therefore remain diagnostic and never change the submission result.
 *
 * @since 1.0.9
 */
#[Service]
final class PostCommitQueueNotifier
{
    /**
     * @since 1.0.9
     */
    public function __construct(
        private readonly TriggerCoordinator $triggerCoordinator,
        private readonly EventPublisherInterface $eventPublisher,
    ) {
    }

    /**
     * @since 1.0.9
     */
    public function notifyQueued(int $mailLogId, string $queueName): void
    {
        try {
            $this->triggerCoordinator->trigger();
        } catch (Throwable $throwable) {
            $this->publishSafely('a!jooosi-mail/queue:trigger.failed', $throwable, $mailLogId);
        }

        try {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:queued', $mailLogId, $queueName);
        } catch (Throwable $throwable) {
            $this->publishSafely(
                'a!jooosi-mail/mail:queued.notification-failed',
                $throwable,
                $mailLogId,
                $queueName,
            );
        }
    }

    /**
     * @since 1.0.9
     */
    private function publishSafely(string $hookName, mixed ...$arguments): void
    {
        try {
            $this->eventPublisher->doAction($hookName, ...$arguments);
        } catch (Throwable) {
        }
    }
}

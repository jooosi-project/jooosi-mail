<?php

declare(strict_types=1);

namespace JooosiMail\Queue\Worker;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Queue\Maintenance\QueueMaintenanceService;
use JooosiMail\Queue\Message\SendEmailMessage;
use JooosiMail\Queue\Retry\RetryDecider;
use JooosiMail\Queue\Transport\DatabaseReceiver;
use JooosiMail\Queue\Transport\DatabaseTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Throwable;

/**
 * Small WordPress-friendly worker for the Jooosi Mail queue.
 *
 * @since 0.1.0
 */
#[Service]
final class QueueWorker
{
    public function __construct(
        private readonly DatabaseReceiver $databaseReceiver,
        private readonly MessageBusInterface $messageBus,
        private readonly RetryDecider $retryDecider,
        private readonly MailLogRepository $mailLogRepository,
        private readonly EventPublisherInterface $eventPublisher,
        private readonly QueueMaintenanceService $queueMaintenanceService,
        private readonly MailLogRetentionService $mailLogRetentionService,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function run(int $limit = 25, int $timeLimit = 20): int
    {
        $processed = 0;
        $startedAt = time();
        $releasedStaleClaims = $this->queueMaintenanceService->releaseStaleClaims();

        if ($releasedStaleClaims > 0) {
            $this->eventPublisher->doAction('a!jooosi-mail/queue:stale-claims.released', $releasedStaleClaims);
        }

        while ((time() - $startedAt) < $timeLimit) {
            $envelopes = $this->databaseReceiver->receive($limit);

            if ($envelopes === []) {
                break;
            }

            foreach ($envelopes as $index => $envelope) {
                if ((time() - $startedAt) >= $timeLimit) {
                    $this->releaseUnprocessed(array_slice($envelopes, $index));

                    break 2;
                }

                $attemptEnvelope = $this->databaseReceiver->beginAttempt($envelope);

                if (! $attemptEnvelope instanceof Envelope) {
                    $this->eventPublisher->doAction('a!jooosi-mail/queue:message.claim-lost', $envelope);

                    continue;
                }

                try {
                    $this->messageBus->dispatch($attemptEnvelope->with(new ReceivedStamp(DatabaseTransport::NAME)));

                    if ($this->databaseReceiver->ackClaimed($attemptEnvelope)) {
                        $processed++;
                    }
                } catch (Throwable $throwable) {
                    $this->handleFailure($attemptEnvelope, $throwable);
                }
            }
        }

        return $processed;
    }

    /**
     * @param list<Envelope> $envelopes
     *
     * @since 0.1.0
     */
    private function releaseUnprocessed(array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->databaseReceiver->release($envelope);
        }
    }

    /**
     * @since 0.1.0
     */
    private function handleFailure(Envelope $envelope, Throwable $throwable): void
    {
        if ($this->retryDecider->shouldRetry($envelope, $throwable)) {
            $delaySeconds = $this->retryDecider->getDelaySeconds($envelope, $throwable);

            if (! $this->databaseReceiver->reschedule($envelope, $throwable->getMessage(), $delaySeconds)) {
                $this->eventPublisher->doAction('a!jooosi-mail/queue:message.claim-lost', $envelope, $throwable);

                return;
            }

            $this->eventPublisher->doAction('a!jooosi-mail/queue:message.retrying', $envelope, $throwable, $delaySeconds);

            return;
        }

        if (! $this->databaseReceiver->rejectClaimed($envelope)) {
            $this->eventPublisher->doAction('a!jooosi-mail/queue:message.claim-lost', $envelope, $throwable);

            return;
        }

        $this->markMailFailed($envelope, $throwable);
        $this->eventPublisher->doAction('a!jooosi-mail/queue:message.failed', $envelope, $throwable);
    }

    /**
     * @since 0.1.0
     */
    private function markMailFailed(Envelope $envelope, Throwable $throwable): void
    {
        $message = $envelope->getMessage();

        if (! $message instanceof SendEmailMessage) {
            return;
        }

        try {
            $this->mailLogRepository->markFailed($message->mailLogId, $throwable->getMessage());
            $this->eventPublisher->doAction('a!jooosi-mail/mail:failed', $message->mailLogId, $throwable->getMessage());
            $this->cleanupTerminalLog($message->mailLogId);
        } catch (Throwable $failureThrowable) {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:failed-log.failed', $message->mailLogId, $throwable, $failureThrowable);
        }
    }

    /**
     * @since 0.1.0
     */
    private function cleanupTerminalLog(int $mailLogId): void
    {
        try {
            $this->mailLogRetentionService->cleanupTerminalLog($mailLogId);
        } catch (Throwable $throwable) {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:retention-cleanup.failed', $mailLogId, $throwable);
        }
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Submission;

use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Delivery\DeliveryService;
use JooosiMail\Mail\Logging\MailLifecycleLogger;
use JooosiMail\Mail\Routing\DeliveryMode;
use JooosiMail\Mail\Routing\RoutingPolicyResolver;
use JooosiMail\Mail\ValueObject\MailRequest;
use JooosiMail\Mail\ValueObject\MailSubmissionResult;
use JooosiMail\Queue\Message\SendEmailMessage;
use JooosiMail\Queue\Stamp\QueuePriorityStamp;
use JooosiMail\Queue\Transport\DatabaseTransport;
use JooosiMail\Queue\Trigger\TriggerCoordinator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Throwable;

/**
 * Submits normalized mail for immediate delivery or durable queue processing.
 *
 * @since 1.0.8
 */
#[Service]
final class MailSubmissionService
{
    private readonly PostCommitQueueNotifier $postCommitQueueNotifier;

    /**
     * @since 1.0.8
     */
    public function __construct(
        private readonly RoutingPolicyResolver $routingPolicyResolver,
        private readonly MailLifecycleLogger $mailLifecycleLogger,
        private readonly DeliveryService $deliveryService,
        private readonly MessageBusInterface $messageBus,
        TriggerCoordinator $triggerCoordinator,
        private readonly DbalConnection $connection,
        EventPublisherInterface $eventPublisher,
        ?PostCommitQueueNotifier $postCommitQueueNotifier = null,
    ) {
        $this->postCommitQueueNotifier = $postCommitQueueNotifier
            ?? new PostCommitQueueNotifier($triggerCoordinator, $eventPublisher);
    }

    /**
     * Returns whether immediate delivery succeeded or the message was queued.
     *
     * @since 1.0.8
     */
    public function submit(MailRequest $mailRequest): bool
    {
        return $this->submitWithResult($mailRequest)->accepted;
    }

    /**
     * Submits mail and returns the new lifecycle row identifier.
     *
     * @since 1.0.9
     */
    public function submitWithResult(MailRequest $mailRequest): MailSubmissionResult
    {
        $deliveryPlan = $this->routingPolicyResolver->resolve($mailRequest);

        if ($deliveryPlan->mode === DeliveryMode::Sync) {
            $mailLogId = $this->mailLifecycleLogger->create($mailRequest, $deliveryPlan);
            $deliveryResult = $this->deliveryService->deliver($mailLogId);

            return new MailSubmissionResult(
                mailLogId: $mailLogId,
                accepted: $deliveryResult->successful,
                error: $deliveryResult->error,
            );
        }

        $stamps = [
            new TransportNamesStamp([DatabaseTransport::NAME]),
            new QueuePriorityStamp($deliveryPlan->priority),
        ];

        if ($deliveryPlan->delaySeconds > 0) {
            $stamps[] = new DelayStamp($deliveryPlan->delaySeconds * 1000);
        }

        $this->connection->beginTransaction();

        try {
            $mailLogId = $this->mailLifecycleLogger->create($mailRequest, $deliveryPlan);
            $this->messageBus->dispatch(new SendEmailMessage($mailLogId), $stamps);
            $this->connection->commit();
        } catch (Throwable $throwable) {
            $this->connection->rollBack();

            throw $throwable;
        }

        $this->postCommitQueueNotifier->notifyQueued($mailLogId, DatabaseTransport::NAME);

        return new MailSubmissionResult(mailLogId: $mailLogId, accepted: true);
    }
}

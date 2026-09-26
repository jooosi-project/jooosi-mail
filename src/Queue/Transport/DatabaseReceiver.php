<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Queue\Logging\QueueAttemptRepository;
use JooosiMail\Queue\State\QueueClaim;
use JooosiMail\Queue\State\QueueClock;
use JooosiMail\Queue\State\QueueMessageRepository;
use JooosiMail\Queue\Stamp\DatabaseMessageStamp;
use JooosiMail\Queue\Worker\QueueWorkerIdentity;
use Override;
use JooosiMailDeps\Symfony\Component\Messenger\Envelope;
use JooosiMailDeps\Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use JooosiMailDeps\Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;
/**
 * Claims and acknowledges queued envelopes for the database transport.
 *
 * @since 0.1.0
 */
#[Service]
final class DatabaseReceiver implements ReceiverInterface
{
    public function __construct(private readonly QueueMessageRepository $queueMessageRepository, private readonly QueueAttemptRepository $queueAttemptRepository, private readonly QueueClock $queueClock, private readonly QueueWorkerIdentity $workerIdentity, private readonly EventPublisherInterface $eventPublisher, private readonly SerializerInterface $serializer)
    {
    }
    /**
     * @return iterable<Envelope>
     *
     * @since 0.1.0
     */
    #[Override]
    public function get(): iterable
    {
        return $this->receive();
    }
    /**
     * @return list<Envelope>
     *
     * @since 0.1.0
     */
    public function receive(int $limit = 25): array
    {
        $rows = $this->queueMessageRepository->findReady($limit);
        $envelopes = [];
        $claim = QueueClaim::create($this->queueClock->now(), $this->workerIdentity->id());
        foreach ($rows as $row) {
            if (!$this->queueMessageRepository->claim((int) $row['id'], $claim)) {
                continue;
            }
            $encodedEnvelope = ['body' => (string) $row['body'], 'headers' => json_decode((string) ($row['headers_json'] ?? '{}'), \true) ?: []];
            try {
                $envelope = $this->serializer->decode($encodedEnvelope);
            } catch (Throwable $throwable) {
                $this->recordDecodeFailure((int) $row['id'], (int) $row['attempt_count'], (int) ($row['max_attempts'] ?? 3), (string) ($row['queue_name'] ?? \JooosiMail\Queue\Transport\DatabaseTransport::NAME), $claim->claimedBy, $claim->workerId, $throwable->getMessage());
                continue;
            }
            $envelope = $envelope->with(new TransportMessageIdStamp((string) $row['id']))->with(new DatabaseMessageStamp(messageId: (int) $row['id'], attemptCount: (int) $row['attempt_count'], maxAttempts: (int) ($row['max_attempts'] ?? 3), queueName: (string) ($row['queue_name'] ?? \JooosiMail\Queue\Transport\DatabaseTransport::NAME), claimedBy: $claim->claimedBy, workerId: $claim->workerId));
            $message = $envelope->getMessage();
            if ($message instanceof MessageDecodingFailedException) {
                $this->recordDecodeFailure((int) $row['id'], (int) $row['attempt_count'], (int) ($row['max_attempts'] ?? 3), (string) ($row['queue_name'] ?? \JooosiMail\Queue\Transport\DatabaseTransport::NAME), $claim->claimedBy, $claim->workerId, $message->getMessage());
                continue;
            }
            $envelopes[] = $envelope;
        }
        return $envelopes;
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function ack(Envelope $envelope): void
    {
        $this->ackClaimed($envelope);
    }
    /**
     * @since 0.1.0
     */
    public function ackClaimed(Envelope $envelope): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        return $this->queueMessageRepository->ack($stamp);
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function reject(Envelope $envelope): void
    {
        $this->rejectClaimed($envelope);
    }
    /**
     * Rejects a message only while this receiver still owns its processing claim.
     * Omitting the error preserves the last recorded failure for Messenger callers.
     *
     * @since 0.1.0
     */
    public function rejectClaimed(Envelope $envelope, ?string $error = null): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        return $this->queueMessageRepository->reject($stamp, $error);
    }
    /**
     * @since 0.1.0
     */
    public function beginAttempt(Envelope $envelope): ?Envelope
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return null;
        }
        $attemptCount = $this->queueMessageRepository->beginAttempt($stamp);
        if ($attemptCount === null) {
            return null;
        }
        $this->recordAttemptStarted($stamp->messageId, $attemptCount, $stamp->claimedBy, $stamp->workerId);
        return $envelope->with(new DatabaseMessageStamp(messageId: $stamp->messageId, attemptCount: $attemptCount, maxAttempts: $stamp->maxAttempts, queueName: $stamp->queueName, claimedBy: $stamp->claimedBy, workerId: $stamp->workerId));
    }
    /**
     * Records decode failures as queue attempts even though they never reach the worker handler.
     *
     * @since 1.0.9
     */
    private function recordDecodeFailure(int $messageId, int $attemptCount, int $maxAttempts, string $queueName, string $claimedBy, string $workerId, string $error): void
    {
        $stamp = new DatabaseMessageStamp(messageId: $messageId, attemptCount: $attemptCount, maxAttempts: $maxAttempts, queueName: $queueName, claimedBy: $claimedBy, workerId: $workerId);
        $nextAttemptCount = $this->queueMessageRepository->beginAttempt($stamp);
        if ($nextAttemptCount === null) {
            return;
        }
        $this->recordAttemptStarted($messageId, $nextAttemptCount, $claimedBy, $workerId);
        $markedFailed = $this->queueMessageRepository->markDecodeFailed($messageId, $claimedBy, $error);
        $this->recordAttemptFinished($messageId, $claimedBy, $markedFailed ? 'failed' : 'claim_lost', $error);
    }
    /**
     * @since 1.0.9
     */
    private function recordAttemptStarted(int $messageId, int $attemptNumber, string $claimedBy, string $workerId): void
    {
        try {
            $this->queueAttemptRepository->recordStarted($messageId, $attemptNumber, $claimedBy, $workerId);
        } catch (Throwable $throwable) {
            $this->publishAttemptLogFailure($messageId, $throwable);
        }
    }
    /**
     * @since 1.0.9
     */
    private function recordAttemptFinished(int $messageId, string $claimedBy, string $outcome, ?string $error): void
    {
        try {
            $this->queueAttemptRepository->finish($messageId, $claimedBy, $outcome, $error);
        } catch (Throwable $throwable) {
            $this->publishAttemptLogFailure($messageId, $throwable);
        }
    }
    /**
     * @since 1.0.9
     */
    private function publishAttemptLogFailure(int $messageId, Throwable $throwable): void
    {
        try {
            $this->eventPublisher->doAction('a!jooosi-mail/queue:attempt-log.failed', $messageId, $throwable);
        } catch (Throwable) {
        }
    }
    /**
     * @since 0.1.0
     */
    public function reschedule(Envelope $envelope, string $error, int $delaySeconds): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        return $this->queueMessageRepository->reschedule($stamp, $error, $delaySeconds);
    }
    /**
     * Releases a claimed message that was never dispatched.
     *
     * @since 0.1.0
     */
    public function release(Envelope $envelope): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        return $this->queueMessageRepository->release($stamp);
    }
}

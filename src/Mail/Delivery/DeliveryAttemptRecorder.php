<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Delivery;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\Routing\ConnectionCircuitBreaker;
use Throwable;
/**
 * Records delivery attempts and updates connection health.
 *
 * @since 1.0.9
 */
#[Service]
final class DeliveryAttemptRecorder
{
    public function __construct(private readonly MailAttemptRepository $mailAttemptRepository, private readonly MailLogRepository $mailLogRepository, private readonly ConnectionCircuitBreaker $connectionCircuitBreaker, private readonly EventPublisherInterface $eventPublisher)
    {
    }
    /**
     * @since 1.0.9
     */
    public function recordAccepted(int $mailLogId, int $connectionId, ?string $transportMessageId, ?string $debug): void
    {
        $recordedAttempt = \false;
        $markedSent = \false;
        $persistenceErrors = [];
        try {
            $this->mailAttemptRepository->record(mailLogId: $mailLogId, connectionId: $connectionId, status: 'sent', debug: $debug, transportMessageId: $transportMessageId);
            $recordedAttempt = \true;
        } catch (Throwable $throwable) {
            $persistenceErrors[] = $throwable;
        }
        try {
            $this->mailLogRepository->markSent($mailLogId, $connectionId, $transportMessageId);
            $markedSent = \true;
        } catch (Throwable $throwable) {
            $persistenceErrors[] = $throwable;
        }
        if (!$recordedAttempt && !$markedSent) {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:sent.persistence-failed', $mailLogId, $connectionId, $transportMessageId, $persistenceErrors);
        }
    }
    /**
     * @since 1.0.9
     */
    public function recordSuccess(Connection $connection): void
    {
        try {
            $this->connectionCircuitBreaker->recordSuccess($connection);
        } catch (Throwable $throwable) {
            $this->eventPublisher->doAction('a!jooosi-mail/routing:success-record.failed', $connection, $throwable);
        }
    }
    /**
     * @since 1.0.9
     */
    public function recordFailure(int $mailLogId, int $connectionId, Connection $connection, Throwable $throwable): void
    {
        try {
            $this->mailAttemptRepository->record(mailLogId: $mailLogId, connectionId: $connectionId, status: 'failed', error: $throwable->getMessage(), debug: method_exists($throwable, 'getDebug') ? (string) call_user_func([$throwable, 'getDebug']) : null);
        } catch (Throwable $loggingThrowable) {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:failed.connection-log-failed', $mailLogId, $connectionId, $throwable, $loggingThrowable);
        }
        try {
            $this->connectionCircuitBreaker->recordFailure($connection, $throwable);
        } catch (Throwable $circuitBreakerThrowable) {
            $this->eventPublisher->doAction('a!jooosi-mail/routing:failure-record.failed', $connection, $throwable, $circuitBreakerThrowable);
        }
        $this->eventPublisher->doAction('a!jooosi-mail/mail:failed.connection', $mailLogId, $connectionId, $throwable);
    }
}

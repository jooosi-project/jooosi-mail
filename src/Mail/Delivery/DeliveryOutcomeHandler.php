<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Delivery;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;
use JooosiMail\Mail\ValueObject\DeliveryResult;
use Throwable;
/**
 * Handles terminal delivery outcomes and retention notifications.
 *
 * @since 1.0.9
 */
#[Service]
final class DeliveryOutcomeHandler
{
    public function __construct(private readonly MailLogRepository $mailLogRepository, private readonly ConnectionStatusReporter $connectionStatusReporter, private readonly EventPublisherInterface $eventPublisher, private readonly MailLogRetentionService $mailLogRetentionService)
    {
    }
    /**
     * @since 1.0.9
     */
    public function handleNoAvailableConnections(int $mailLogId, bool $finalizeFailures): DeliveryResult
    {
        $summary = $this->connectionStatusReporter->summarizeActiveConnections();
        if (($summary['active_connections'] ?? 0) <= 0) {
            $error = 'No active connections are configured.';
            if ($finalizeFailures) {
                $this->mailLogRepository->markFailed($mailLogId, $error);
                $this->eventPublisher->doAction('a!jooosi-mail/mail:failed', $mailLogId, $error);
                $this->cleanupTerminalLog($mailLogId);
            } else {
                $this->mailLogRepository->markDeferred($mailLogId, $error);
            }
            return new DeliveryResult(successful: \false, error: $error);
        }
        $retryAfterSeconds = isset($summary['next_available_in_seconds']) ? (int) $summary['next_available_in_seconds'] : null;
        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            $error = sprintf('All active connections are temporarily unavailable. Retry in %d second(s).', $retryAfterSeconds);
            $this->mailLogRepository->markDeferred($mailLogId, $error);
            $this->eventPublisher->doAction('a!jooosi-mail/mail:deferred', $mailLogId, $retryAfterSeconds);
            return new DeliveryResult(successful: \false, error: $error, temporaryFailure: \true, retryAfterSeconds: $retryAfterSeconds);
        }
        $error = 'No available connections passed routing checks.';
        if ($finalizeFailures) {
            $this->mailLogRepository->markFailed($mailLogId, $error);
            $this->eventPublisher->doAction('a!jooosi-mail/mail:failed', $mailLogId, $error);
            $this->cleanupTerminalLog($mailLogId);
        } else {
            $this->mailLogRepository->markDeferred($mailLogId, $error);
        }
        return new DeliveryResult(successful: \false, error: $error);
    }
    /**
     * @since 1.0.9
     */
    public function handleExhaustedCandidates(int $mailLogId, bool $finalizeFailures): DeliveryResult
    {
        $error = 'No connection could deliver this message.';
        if ($finalizeFailures) {
            $this->mailLogRepository->markFailed($mailLogId, $error);
            $this->eventPublisher->doAction('a!jooosi-mail/mail:failed', $mailLogId, $error);
            $this->cleanupTerminalLog($mailLogId);
        } else {
            $this->mailLogRepository->markDeferred($mailLogId, $error);
        }
        return new DeliveryResult(successful: \false, error: $error);
    }
    /**
     * @since 1.0.9
     */
    public function dispatchMailSentAction(int $mailLogId, int $connectionId, ?string $transportMessageId): void
    {
        try {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:sent', $mailLogId, $connectionId, $transportMessageId);
        } catch (Throwable) {
        }
    }
    /**
     * @since 1.0.9
     */
    public function cleanupTerminalLog(int $mailLogId): void
    {
        try {
            $this->mailLogRetentionService->cleanupTerminalLog($mailLogId);
        } catch (Throwable $throwable) {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:retention-cleanup.failed', $mailLogId, $throwable);
        }
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Delivery;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Mail\ValueObject\DeliveryResult;
use Throwable;
/**
 * Reconciles a provider-accepted attempt whose mail log was not finalized.
 *
 * @since 1.0.9
 */
#[Service]
final class DeliveryMailLogReconciler
{
    public function __construct(private readonly MailLogRepository $mailLogRepository, private readonly MailLogRetentionService $mailLogRetentionService, private readonly EventPublisherInterface $eventPublisher)
    {
    }
    /**
     * @param array<string, mixed> $sentAttempt
     *
     * @since 1.0.9
     */
    public function reconcile(int $mailLogId, array $sentAttempt): DeliveryResult
    {
        $connectionId = (int) ($sentAttempt['connection_id'] ?? 0);
        $transportMessageId = isset($sentAttempt['transport_message_id']) ? (string) $sentAttempt['transport_message_id'] : null;
        if ($connectionId > 0) {
            try {
                $this->mailLogRepository->markSent($mailLogId, $connectionId, $transportMessageId);
                $this->cleanupTerminalLog($mailLogId);
            } catch (Throwable $throwable) {
                $this->eventPublisher->doAction('a!jooosi-mail/mail:sent.reconcile-failed', $mailLogId, $connectionId, $throwable);
            }
        }
        return new DeliveryResult(successful: \true, connectionId: $connectionId > 0 ? $connectionId : null, transportMessageId: $transportMessageId);
    }
    /**
     * @since 1.0.9
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

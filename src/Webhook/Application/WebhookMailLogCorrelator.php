<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Application;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Logging\MailLogRepository;
/**
 * Correlates normalized provider events with the current connection's mail log.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookMailLogCorrelator
{
    public function __construct(private readonly MailAttemptRepository $mailAttemptRepository, private readonly MailLogRepository $mailLogRepository)
    {
    }
    /**
     * @param array<string, mixed> $event
     *
     * @since 1.0.9
     */
    public function correlate(int $connectionId, array $event): ?int
    {
        $mailLogId = isset($event['mail_log_id']) ? (int) $event['mail_log_id'] : null;
        $transportMessageId = $this->normalizeIdentifier($event['transport_message_id'] ?? null);
        if ($mailLogId === null && $transportMessageId !== null) {
            $mailLogId = $this->mailAttemptRepository->findMailLogIdByTransportMessageId($connectionId, $transportMessageId) ?? $this->mailLogRepository->findIdByTransportMessageId($transportMessageId, $connectionId);
        }
        return $mailLogId;
    }
    /**
     * @since 1.0.9
     */
    public function normalizeIdentifier(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }
}

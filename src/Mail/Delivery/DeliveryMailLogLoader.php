<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Delivery;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\ValueObject\MailRequest;

/**
 * Loads the persisted data required to deliver a mail log.
 *
 * @since 1.0.9
 */
#[Service]
final class DeliveryMailLogLoader
{
    public function __construct(
        private readonly MailLogRepository $mailLogRepository,
        private readonly MailAttemptRepository $mailAttemptRepository,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     *
     * @since 1.0.9
     */
    public function find(int $mailLogId): ?array
    {
        $mailLog = $this->mailLogRepository->find($mailLogId);

        return is_array($mailLog) ? $mailLog : null;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @since 1.0.9
     */
    public function findLatestSentAttempt(int $mailLogId): ?array
    {
        $sentAttempt = $this->mailAttemptRepository->findLatestSent($mailLogId);

        return is_array($sentAttempt) ? $sentAttempt : null;
    }

    /**
     * @param array<string, mixed> $mailLog
     *
     * @since 1.0.9
     */
    public function createRequest(array $mailLog): MailRequest
    {
        return MailRequest::fromArray(json_decode((string) ($mailLog['payload_json'] ?? '{}'), true) ?: []);
    }
}

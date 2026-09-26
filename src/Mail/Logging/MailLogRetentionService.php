<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Logging;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Logging\QueueAttemptRepository;
/**
 * Applies email log and terminal queue attempt retention policies.
 *
 * @since 0.1.0
 */
#[Service]
final class MailLogRetentionService
{
    public function __construct(private readonly \JooosiMail\Mail\Logging\MailLogRetentionPolicy $retentionPolicy, private readonly \JooosiMail\Mail\Logging\MailLogRepository $mailLogRepository, private readonly QueueAttemptRepository $queueAttemptRepository)
    {
    }
    /**
     * Deletes a terminal log immediately when email logging is disabled.
     *
     * @since 0.1.0
     */
    public function cleanupTerminalLog(int $mailLogId): void
    {
        if ($this->retentionPolicy->isEmailLoggingEnabled()) {
            return;
        }
        $this->mailLogRepository->deleteCascade($mailLogId);
    }
    /**
     * @since 0.1.0
     */
    public function pruneExpired(int $limit = 500): int
    {
        $retentionDays = $this->retentionPolicy->getRetentionDays();
        $deletedQueueAttempts = $retentionDays === null ? 0 : $this->queueAttemptRepository->pruneTerminalHistoryOlderThan($retentionDays, $limit);
        if (!$this->retentionPolicy->isEmailLoggingEnabled()) {
            return $deletedQueueAttempts + $this->mailLogRepository->deleteTerminalLogs($limit);
        }
        if ($retentionDays === null) {
            return $deletedQueueAttempts;
        }
        return $deletedQueueAttempts + $this->mailLogRepository->deleteTerminalLogsOlderThan($retentionDays, $limit);
    }
}

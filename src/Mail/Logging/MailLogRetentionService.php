<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Logging;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Applies email log deletion after terminal delivery and retention windows.
 *
 * @since 0.1.0
 */
#[Service]
final class MailLogRetentionService
{
    public function __construct(private readonly \JooosiMail\Mail\Logging\MailLogRetentionPolicy $retentionPolicy, private readonly \JooosiMail\Mail\Logging\MailLogRepository $mailLogRepository)
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
        if (!$this->retentionPolicy->isEmailLoggingEnabled()) {
            return $this->mailLogRepository->deleteTerminalLogs($limit);
        }
        $retentionDays = $this->retentionPolicy->getRetentionDays();
        if ($retentionDays === null) {
            return 0;
        }
        return $this->mailLogRepository->deleteTerminalLogsOlderThan($retentionDays, $limit);
    }
}

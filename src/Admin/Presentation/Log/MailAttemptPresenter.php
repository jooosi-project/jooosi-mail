<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Presentation\Log;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Projects mail delivery attempt rows for admin summaries.
 *
 * @since 1.0.9
 */
#[Service]
final class MailAttemptPresenter
{
    /**
     * @param list<array<string, mixed>> $attempts
     * @return list<array<string, mixed>>
     *
 * @since 1.0.9
     */
    public function presentMany(array $attempts): array
    {
        return array_map(static fn (array $attempt): array => [
            'id' => (int) ($attempt['id'] ?? 0),
            'mailLogId' => (int) ($attempt['mail_log_id'] ?? 0),
            'connectionId' => (int) ($attempt['connection_id'] ?? 0),
            'connectionName' => (string) ($attempt['connection_name'] ?? ''),
            'status' => (string) ($attempt['status'] ?? ''),
            'errorMessage' => isset($attempt['error_message']) ? (string) $attempt['error_message'] : null,
            'transportMessageId' => isset($attempt['transport_message_id']) ? (string) $attempt['transport_message_id'] : null,
            'startedAt' => isset($attempt['started_at']) ? (string) $attempt['started_at'] : null,
            'finishedAt' => isset($attempt['finished_at']) ? (string) $attempt['finished_at'] : null,
        ], $attempts);
    }
}

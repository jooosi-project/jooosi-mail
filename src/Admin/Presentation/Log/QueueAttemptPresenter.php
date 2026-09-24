<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Presentation\Log;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Projects queue attempt rows for the admin API.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueAttemptPresenter
{
    /**
     * @param list<array<string, mixed>> $attempts
     * @return array<int, list<array{
     *     sequenceNumber: int,
     *     attemptNumber: int,
     *     outcome: string,
     *     workerId: ?string,
     *     errorMessage: ?string,
     *     retryDelaySeconds: ?int,
     *     startedAt: string,
     *     finishedAt: ?string
     * }>>
     *
     * @since 1.0.9
     */
    public function presentGroupedByQueueMessage(array $attempts): array
    {
        $presentedAttempts = [];

        foreach ($attempts as $attempt) {
            $queueMessageId = (int) ($attempt['queue_message_id'] ?? 0);
            $sequenceNumber = count($presentedAttempts[$queueMessageId] ?? []) + 1;
            $presentedAttempts[$queueMessageId][] = [
                'sequenceNumber' => $sequenceNumber,
                'attemptNumber' => (int) ($attempt['attempt_number'] ?? 0),
                'outcome' => (string) ($attempt['outcome'] ?? ''),
                'workerId' => isset($attempt['worker_id']) && (string) $attempt['worker_id'] !== ''
                    ? (string) $attempt['worker_id']
                    : null,
                'errorMessage' => isset($attempt['error_message']) && (string) $attempt['error_message'] !== ''
                    ? (string) $attempt['error_message']
                    : null,
                'retryDelaySeconds' => isset($attempt['retry_delay_seconds'])
                    ? (int) $attempt['retry_delay_seconds']
                    : null,
                'startedAt' => (string) ($attempt['started_at'] ?? ''),
                'finishedAt' => isset($attempt['finished_at']) && (string) $attempt['finished_at'] !== ''
                    ? (string) $attempt['finished_at']
                    : null,
            ];
        }

        return $presentedAttempts;
    }
}

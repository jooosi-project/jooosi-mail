<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Presentation\Log;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Message\SendEmailMessage;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;

/**
 * Projects queue message rows into the public admin API representation.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueMessagePresenter
{
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     *
 * @since 1.0.9
     */
    public function presentMany(array $messages, bool $includeMailLogId = true): array
    {
        return array_map(
            fn (array $message): array => $this->present($message, $includeMailLogId),
            $messages,
        );
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     *
 * @since 1.0.9
     */
    public function present(array $message, bool $includeMailLogId = true): array
    {
        $payload = [
            'id' => (int) ($message['id'] ?? 0),
        ];

        if ($includeMailLogId) {
            $payload['mailLogId'] = $this->extractMailLogId($message['body'] ?? null);
        }

        $status = (string) ($message['status'] ?? '');
        $workerId = isset($message['claimed_worker_id']) ? trim((string) $message['claimed_worker_id']) : '';

        return $payload + [
            'status' => $status,
            'priority' => (int) ($message['priority'] ?? 0),
            'attemptCount' => (int) ($message['attempt_count'] ?? 0),
            'maxAttempts' => (int) ($message['max_attempts'] ?? 0),
            'lastError' => isset($message['last_error']) ? (string) $message['last_error'] : null,
            'availableAt' => isset($message['available_at']) ? (string) $message['available_at'] : null,
            'claimedAt' => isset($message['claimed_at']) ? (string) $message['claimed_at'] : null,
            'workerId' => $status === 'processing' && $workerId !== '' ? $workerId : null,
            'processedAt' => isset($message['processed_at']) ? (string) $message['processed_at'] : null,
            'createdAt' => isset($message['created_at']) ? (string) $message['created_at'] : null,
            'updatedAt' => isset($message['updated_at']) ? (string) $message['updated_at'] : null,
        ];
    }

    /**
 * @since 1.0.9
     */
    private function extractMailLogId(mixed $body): ?int
    {
        if (! is_string($body) || trim($body) === '') {
            return null;
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $body,
                'headers' => [],
            ]);
        } catch (Throwable) {
            return null;
        }

        $message = $envelope->getMessage();

        return $message instanceof SendEmailMessage ? $message->mailLogId : null;
    }
}

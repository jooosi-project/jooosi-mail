<?php

declare(strict_types=1);

namespace JooosiMail\Queue\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Queue\Stamp\QueuePriorityStamp;
use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Persists queued envelopes into the Jooosi Mail queue table.
 *
 * @since 0.1.0
 */
#[Service]
final class DatabaseSender implements SenderInterface
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
        private readonly SerializerInterface $serializer,
        private readonly OptionStore $optionStore,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Override]
    public function send(Envelope $envelope): Envelope
    {
        $encodedEnvelope = $this->serializer->encode($envelope);
        $delayStamp = $envelope->last(DelayStamp::class);
        $priorityStamp = $envelope->last(QueuePriorityStamp::class);
        $delaySeconds = (int) ceil(($delayStamp?->getDelay() ?? 0) / 1000);
        $availableAt = gmdate('Y-m-d H:i:s', time() + $delaySeconds);
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->insert($this->tableNameResolver->resolve('queue_messages'), [
            'body' => $encodedEnvelope['body'],
            'headers_json' => wp_json_encode($encodedEnvelope['headers'] ?? []),
            'queue_name' => DatabaseTransport::NAME,
            'status' => 'pending',
            'priority' => $priorityStamp?->priority ?? 10,
            'available_at' => $availableAt,
            'attempt_count' => 0,
            'max_attempts' => $this->resolveMaxAttempts(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $envelope->with(new TransportMessageIdStamp((string) $this->connection->lastInsertId()));
    }

    /**
     * @since 0.1.0
     */
    private function resolveMaxAttempts(): int
    {
        return max(1, (int) $this->optionStore->get('settings.queue.retry.max_retries', 3) + 1);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Transport;

use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Queue\Stamp\DatabaseMessageStamp;
use Override;
use JooosiMailDeps\Symfony\Component\Messenger\Envelope;
use JooosiMailDeps\Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use JooosiMailDeps\Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;
/**
 * Claims and acknowledges queued envelopes for the database transport.
 *
 * @since 0.1.0
 */
#[Service]
final class DatabaseReceiver implements ReceiverInterface
{
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver, private readonly SerializerInterface $serializer)
    {
    }
    /**
     * @return iterable<Envelope>
     *
     * @since 0.1.0
     */
    #[Override]
    public function get(): iterable
    {
        return $this->receive();
    }
    /**
     * @return list<Envelope>
     *
     * @since 0.1.0
     */
    public function receive(int $limit = 25): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s WHERE status = :status AND available_at <= :available_at ORDER BY priority ASC, id ASC LIMIT %d', $this->tableNameResolver->resolve('queue_messages'), $limit), ['status' => 'pending', 'available_at' => gmdate('Y-m-d H:i:s')]);
        $envelopes = [];
        $claimedAt = gmdate('Y-m-d H:i:s');
        $claimedBy = wp_generate_uuid4();
        foreach ($rows as $row) {
            $updated = $this->connection->update($this->tableNameResolver->resolve('queue_messages'), ['status' => 'processing', 'claimed_at' => $claimedAt, 'claimed_by' => $claimedBy, 'updated_at' => $claimedAt], ['id' => (int) $row['id'], 'status' => 'pending']);
            if ($updated !== 1) {
                continue;
            }
            $encodedEnvelope = ['body' => (string) $row['body'], 'headers' => json_decode((string) ($row['headers_json'] ?? '{}'), \true) ?: []];
            try {
                $envelope = $this->serializer->decode($encodedEnvelope)->with(new TransportMessageIdStamp((string) $row['id']))->with(new DatabaseMessageStamp(messageId: (int) $row['id'], attemptCount: (int) $row['attempt_count'], maxAttempts: (int) ($row['max_attempts'] ?? 3), queueName: (string) ($row['queue_name'] ?? \JooosiMail\Queue\Transport\DatabaseTransport::NAME), claimedBy: $claimedBy));
                $message = $envelope->getMessage();
                if ($message instanceof MessageDecodingFailedException) {
                    $this->connection->update($this->tableNameResolver->resolve('queue_messages'), ['status' => 'failed', 'last_error' => $message->getMessage(), 'processed_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => (int) $row['id'], 'status' => 'processing', 'claimed_by' => $claimedBy]);
                    continue;
                }
                $envelopes[] = $envelope;
            } catch (Throwable $throwable) {
                $this->connection->update($this->tableNameResolver->resolve('queue_messages'), ['status' => 'failed', 'last_error' => $throwable->getMessage(), 'processed_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => (int) $row['id'], 'status' => 'processing', 'claimed_by' => $claimedBy]);
            }
        }
        return $envelopes;
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function ack(Envelope $envelope): void
    {
        $this->ackClaimed($envelope);
    }
    /**
     * @since 0.1.0
     */
    public function ackClaimed(Envelope $envelope): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        return $this->connection->update($this->tableNameResolver->resolve('queue_messages'), ['status' => 'completed', 'last_error' => null, 'processed_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')], $this->ownedProcessingCriteria($stamp)) === 1;
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function reject(Envelope $envelope): void
    {
        $this->rejectClaimed($envelope);
    }
    /**
     * @since 0.1.0
     */
    public function rejectClaimed(Envelope $envelope): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        return $this->connection->update($this->tableNameResolver->resolve('queue_messages'), ['status' => 'failed', 'processed_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')], $this->ownedProcessingCriteria($stamp)) === 1;
    }
    /**
     * @since 0.1.0
     */
    public function beginAttempt(Envelope $envelope): ?Envelope
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return null;
        }
        $attemptCount = $stamp->attemptCount + 1;
        $updated = $this->connection->update($this->tableNameResolver->resolve('queue_messages'), ['attempt_count' => $attemptCount, 'updated_at' => gmdate('Y-m-d H:i:s')], $this->ownedProcessingCriteria($stamp));
        if ($updated !== 1) {
            return null;
        }
        return $envelope->with(new DatabaseMessageStamp(messageId: $stamp->messageId, attemptCount: $attemptCount, maxAttempts: $stamp->maxAttempts, queueName: $stamp->queueName, claimedBy: $stamp->claimedBy));
    }
    /**
     * @since 0.1.0
     */
    public function reschedule(Envelope $envelope, string $error, int $delaySeconds): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        return $this->connection->update($this->tableNameResolver->resolve('queue_messages'), ['status' => 'pending', 'available_at' => gmdate('Y-m-d H:i:s', time() + $delaySeconds), 'claimed_at' => null, 'claimed_by' => null, 'last_error' => $error, 'processed_at' => null, 'updated_at' => gmdate('Y-m-d H:i:s')], $this->ownedProcessingCriteria($stamp)) === 1;
    }
    /**
     * Releases a claimed message that was never dispatched.
     *
     * @since 0.1.0
     */
    public function release(Envelope $envelope): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        return $this->connection->update($this->tableNameResolver->resolve('queue_messages'), ['status' => 'pending', 'claimed_at' => null, 'claimed_by' => null, 'updated_at' => gmdate('Y-m-d H:i:s')], $this->ownedProcessingCriteria($stamp)) === 1;
    }
    /**
     * @return array{id: int, status: string, claimed_by: string}
     *
     * @since 0.1.0
     */
    private function ownedProcessingCriteria(DatabaseMessageStamp $stamp): array
    {
        return ['id' => $stamp->messageId, 'status' => 'processing', 'claimed_by' => $stamp->claimedBy];
    }
}

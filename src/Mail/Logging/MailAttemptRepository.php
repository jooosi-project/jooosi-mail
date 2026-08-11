<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Logging;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;

/**
 * Repository for per-connection send attempts.
 *
 * @since 0.1.0
 */
#[Service]
final class MailAttemptRepository
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function record(
        int $mailLogId,
        int $connectionId,
        string $status,
        ?string $error = null,
        ?string $debug = null,
        ?string $transportMessageId = null,
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->insert($this->tableNameResolver->resolve('mail_attempts'), [
            'mail_log_id' => $mailLogId,
            'connection_id' => $connectionId,
            'status' => $status,
            'error_message' => $error,
            'debug_output' => $debug,
            'transport_message_id' => $transportMessageId,
            'started_at' => $now,
            'finished_at' => $now,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function findMailLogIdByTransportMessageId(int $connectionId, string $transportMessageId): ?int
    {
        if ($connectionId <= 0 || $transportMessageId === '') {
            return null;
        }

        $mailLogId = $this->connection->fetchOne(
            sprintf(
                'SELECT mail_log_id FROM %s WHERE connection_id = :connection_id AND transport_message_id = :transport_message_id ORDER BY id DESC LIMIT 1',
                $this->tableNameResolver->resolve('mail_attempts'),
            ),
            [
                'connection_id' => $connectionId,
                'transport_message_id' => $transportMessageId,
            ],
        );

        return is_numeric($mailLogId) ? (int) $mailLogId : null;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @since 0.1.0
     */
    public function findLatestSent(int $mailLogId): ?array
    {
        $row = $this->connection->fetchAssociative(
            sprintf(
                'SELECT * FROM %s WHERE mail_log_id = :mail_log_id AND status = :status ORDER BY id DESC LIMIT 1',
                $this->tableNameResolver->resolve('mail_attempts'),
            ),
            [
                'mail_log_id' => $mailLogId,
                'status' => 'sent',
            ],
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    public function listRecent(int $limit = 20, ?int $mailLogId = null, ?int $connectionId = null, ?string $status = null): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select(
                'a.id',
                'a.mail_log_id',
                'a.connection_id',
                'a.status',
                'a.error_message',
                'a.debug_output',
                'a.transport_message_id',
                'a.started_at',
                'a.finished_at',
                'c.name AS connection_name',
            )
            ->from($this->tableNameResolver->resolve('mail_attempts'), 'a')
            ->leftJoin('a', $this->tableNameResolver->resolve('connections'), 'c', 'c.id = a.connection_id')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($mailLogId !== null) {
            $queryBuilder
                ->andWhere('a.mail_log_id = :mail_log_id')
                ->setParameter('mail_log_id', $mailLogId);
        }

        if ($connectionId !== null) {
            $queryBuilder
                ->andWhere('a.connection_id = :connection_id')
                ->setParameter('connection_id', $connectionId);
        }

        if ($status !== null && $status !== '') {
            $queryBuilder
                ->andWhere('a.status = :status')
                ->setParameter('status', strtolower($status));
        }

        return $queryBuilder->fetchAllAssociative();
    }

    /**
     * @param list<int> $connectionIds
     * @return array<int, int>
     *
     * @since 0.1.0
     */
    public function getHealthScores(array $connectionIds, int $sampleSize = 20): array
    {
        if ($connectionIds === []) {
            return [];
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('connection_id', 'status')
            ->from($this->tableNameResolver->resolve('mail_attempts'))
            ->where('connection_id IN (:connection_ids)')
            ->orderBy('id', 'DESC')
            ->setMaxResults(max($sampleSize * count($connectionIds), 50))
            ->setParameter('connection_ids', array_values($connectionIds), ArrayParameterType::INTEGER)
            ->fetchAllAssociative();

        $attemptsByConnectionId = [];

        foreach ($rows as $row) {
            $connectionId = (int) ($row['connection_id'] ?? 0);

            if ($connectionId <= 0) {
                continue;
            }

            $attemptsByConnectionId[$connectionId] ??= [];

            if (count($attemptsByConnectionId[$connectionId]) >= $sampleSize) {
                continue;
            }

            $attemptsByConnectionId[$connectionId][] = (string) ($row['status'] ?? 'failed');
        }

        $scores = [];

        foreach ($connectionIds as $connectionId) {
            $attempts = $attemptsByConnectionId[$connectionId] ?? [];

            if ($attempts === []) {
                $scores[$connectionId] = 60;
                continue;
            }

            $sampleCount = count($attempts);
            $successCount = count(array_filter($attempts, static fn (string $status): bool => $status === 'sent'));
            $failureCount = $sampleCount - $successCount;
            $successRate = $sampleCount > 0 ? $successCount / $sampleCount : 0.0;
            $score = 40 + (int) round($successRate * 45);
            $score += $attempts[0] === 'sent' ? 10 : -15;
            $score -= min(20, $failureCount * 2);
            $score -= min(15, $this->countLeadingFailures($attempts) * 5);
            $scores[$connectionId] = max(0, min(100, $score));
        }

        return $scores;
    }

    /**
     * @param list<string> $attempts
     *
     * @since 0.1.0
     */
    private function countLeadingFailures(array $attempts): int
    {
        $count = 0;

        foreach ($attempts as $status) {
            if ($status === 'sent') {
                break;
            }

            $count++;
        }

        return $count;
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Routing\State;

use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use Throwable;

/**
 * Persists rolling rate-limit windows for connections.
 *
 * @since 0.1.0
 */
#[Service]
final class RateLimitStateRepository
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
    ) {
    }

    /**
     * @return array{count: int, started_at: int, ends_at: int}
     *
     * @since 0.1.0
     */
    public function get(int $connectionId, string $period, int $windowSeconds): array
    {
        $row = $this->connection->fetchAssociative(
            sprintf(
                'SELECT * FROM %s WHERE connection_id = :connection_id AND period_key = :period_key LIMIT 1',
                $this->tableNameResolver->resolve('connection_rate_limits'),
            ),
            [
                'connection_id' => $connectionId,
                'period_key' => $period,
            ],
        );

        return $this->normalizeState(is_array($row) ? $row : [], $windowSeconds);
    }

    /**
     * @return array{count: int, started_at: int, ends_at: int}
     *
     * @since 0.1.0
     */
    public function increment(int $connectionId, string $period, int $windowSeconds): array
    {
        $this->connection->beginTransaction();

        try {
            $this->ensureStateRowExists($connectionId, $period, $windowSeconds);
            $state = $this->fetchStateForUpdate($connectionId, $period, $windowSeconds);
            $state['count']++;
            $this->save($connectionId, $period, $state);
            $this->connection->commit();

            return $state;
        } catch (Throwable $throwable) {
            $this->connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * @param array<string, int> $limits
     * @param array<string, int> $windowSecondsByPeriod
     *
     * @since 0.1.0
     */
    public function reserve(int $connectionId, array $limits, array $windowSecondsByPeriod): bool
    {
        $activeLimits = array_filter(
            $limits,
            static fn (int $limit, string $period): bool => $limit > 0 && isset($windowSecondsByPeriod[$period]),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($activeLimits === []) {
            return true;
        }

        $this->connection->beginTransaction();

        try {
            $reservedStates = [];

            foreach ($activeLimits as $period => $limit) {
                $windowSeconds = $windowSecondsByPeriod[$period];
                $this->ensureStateRowExists($connectionId, $period, $windowSeconds);
                $state = $this->fetchStateForUpdate($connectionId, $period, $windowSeconds);

                if ($state['count'] >= $limit) {
                    $this->connection->rollBack();

                    return false;
                }

                $state['count']++;
                $reservedStates[$period] = $state;
            }

            foreach ($reservedStates as $period => $state) {
                $this->save($connectionId, $period, $state);
            }

            $this->connection->commit();

            return true;
        } catch (Throwable $throwable) {
            $this->connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * @param array{count: int, started_at: int, ends_at: int} $state
     *
     * @since 0.1.0
     */
    private function save(int $connectionId, string $period, array $state): void
    {
        $table = $this->tableNameResolver->resolve('connection_rate_limits');
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s (connection_id, period_key, usage_count, window_started_at, window_ends_at, created_at, updated_at)
                 VALUES (:connection_id, :period_key, :usage_count, :window_started_at, :window_ends_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                 usage_count = VALUES(usage_count),
                 window_started_at = VALUES(window_started_at),
                 window_ends_at = VALUES(window_ends_at),
                 updated_at = VALUES(updated_at)',
                $table,
            ),
            [
                'connection_id' => $connectionId,
                'period_key' => $period,
                'usage_count' => $state['count'],
                'window_started_at' => gmdate('Y-m-d H:i:s', $state['started_at']),
                'window_ends_at' => gmdate('Y-m-d H:i:s', $state['ends_at']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    /**
     * @since 0.1.0
     */
    private function ensureStateRowExists(int $connectionId, string $period, int $windowSeconds): void
    {
        $now = time();
        $createdAt = gmdate('Y-m-d H:i:s', $now);

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s (connection_id, period_key, usage_count, window_started_at, window_ends_at, created_at, updated_at)
                 VALUES (:connection_id, :period_key, 0, :window_started_at, :window_ends_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE period_key = period_key',
                $this->tableNameResolver->resolve('connection_rate_limits'),
            ),
            [
                'connection_id' => $connectionId,
                'period_key' => $period,
                'window_started_at' => $createdAt,
                'window_ends_at' => gmdate('Y-m-d H:i:s', $now + $windowSeconds),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
        );
    }

    /**
     * @return array{count: int, started_at: int, ends_at: int}
     *
     * @since 0.1.0
     */
    private function fetchStateForUpdate(int $connectionId, string $period, int $windowSeconds): array
    {
        $row = $this->connection->fetchAssociative(
            sprintf(
                'SELECT * FROM %s WHERE connection_id = :connection_id AND period_key = :period_key LIMIT 1 FOR UPDATE',
                $this->tableNameResolver->resolve('connection_rate_limits'),
            ),
            [
                'connection_id' => $connectionId,
                'period_key' => $period,
            ],
        );

        return $this->normalizeState(is_array($row) ? $row : [], $windowSeconds);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{count: int, started_at: int, ends_at: int}
     *
     * @since 0.1.0
     */
    private function normalizeState(array $row, int $windowSeconds): array
    {
        $now = time();
        $startedAt = $this->toTimestamp($row['window_started_at'] ?? null) ?? $now;
        $endsAt = $this->toTimestamp($row['window_ends_at'] ?? null) ?? ($startedAt + $windowSeconds);

        if ($endsAt <= $now) {
            return [
                'count' => 0,
                'started_at' => $now,
                'ends_at' => $now + $windowSeconds,
            ];
        }

        return [
            'count' => max(0, (int) ($row['usage_count'] ?? 0)),
            'started_at' => $startedAt,
            'ends_at' => $endsAt,
        ];
    }

    /**
     * @since 0.1.0
     */
    private function toTimestamp(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }
}

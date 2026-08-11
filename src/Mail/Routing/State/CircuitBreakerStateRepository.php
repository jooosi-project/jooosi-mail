<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Routing\State;

use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use Throwable;

/**
 * Persists circuit-breaker state for connections.
 *
 * @since 0.1.0
 */
#[Service]
final class CircuitBreakerStateRepository
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
    ) {
    }

    /**
     * @return array<string, int|string|null>
     *
     * @since 0.1.0
     */
    public function get(int $connectionId, int $windowSeconds): array
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE connection_id = :connection_id LIMIT 1', $this->tableNameResolver->resolve('connection_circuit_breakers')),
            ['connection_id' => $connectionId],
        );

        return $this->normalizeState(is_array($row) ? $row : [], $windowSeconds);
    }

    /**
     * @return array<string, int|string|null>
     *
     * @since 0.1.0
     */
    public function recordFailure(int $connectionId, int $windowSeconds, string $error): array
    {
        $this->connection->beginTransaction();

        try {
            $this->ensureStateRowExists($connectionId);
            $state = $this->fetchStateForUpdate($connectionId, $windowSeconds);
            $now = time();
            $windowStartedAt = $state['window_started_at'];
            $recentFailureCount = (int) $state['recent_failure_count'];

            if (! is_int($windowStartedAt) || $windowStartedAt <= ($now - $windowSeconds)) {
                $windowStartedAt = $now;
                $recentFailureCount = 0;
            }

            $recentFailureCount++;

            $this->save(
                connectionId: $connectionId,
                recentFailureCount: $recentFailureCount,
                windowStartedAt: $windowStartedAt,
                lastFailureAt: $now,
                blacklistedUntil: is_int($state['blacklisted_until']) ? $state['blacklisted_until'] : null,
                lastErrorMessage: $error,
            );

            $this->connection->commit();

            return [
                'recent_failure_count' => $recentFailureCount,
                'window_started_at' => $windowStartedAt,
                'last_failure_at' => $now,
                'blacklisted_until' => is_int($state['blacklisted_until']) ? $state['blacklisted_until'] : null,
                'last_error_message' => $error,
            ];
        } catch (Throwable $throwable) {
            $this->connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * @since 0.1.0
     */
    public function setBlacklistedUntil(int $connectionId, int $blacklistedUntil, ?string $error = null): void
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE connection_id = :connection_id LIMIT 1', $this->tableNameResolver->resolve('connection_circuit_breakers')),
            ['connection_id' => $connectionId],
        );

        $recentFailureCount = isset($row['recent_failure_count']) ? (int) $row['recent_failure_count'] : 0;
        $windowStartedAt = $this->toTimestamp($row['window_started_at'] ?? null);
        $lastFailureAt = $this->toTimestamp($row['last_failure_at'] ?? null) ?? time();
        $lastErrorMessage = $error ?? (isset($row['last_error_message']) ? (string) $row['last_error_message'] : null);

        $this->save(
            connectionId: $connectionId,
            recentFailureCount: $recentFailureCount,
            windowStartedAt: $windowStartedAt,
            lastFailureAt: $lastFailureAt,
            blacklistedUntil: $blacklistedUntil,
            lastErrorMessage: $lastErrorMessage,
        );
    }

    /**
     * @since 0.1.0
     */
    public function clear(int $connectionId): void
    {
        $this->save(
            connectionId: $connectionId,
            recentFailureCount: 0,
            windowStartedAt: null,
            lastFailureAt: null,
            blacklistedUntil: null,
            lastErrorMessage: null,
        );
    }

    /**
     * @since 0.1.0
     */
    private function save(
        int $connectionId,
        int $recentFailureCount,
        ?int $windowStartedAt,
        ?int $lastFailureAt,
        ?int $blacklistedUntil,
        ?string $lastErrorMessage,
    ): void {
        $table = $this->tableNameResolver->resolve('connection_circuit_breakers');
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s (connection_id, recent_failure_count, window_started_at, last_failure_at, blacklisted_until, last_error_message, created_at, updated_at)
                 VALUES (:connection_id, :recent_failure_count, :window_started_at, :last_failure_at, :blacklisted_until, :last_error_message, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                 recent_failure_count = VALUES(recent_failure_count),
                 window_started_at = VALUES(window_started_at),
                 last_failure_at = VALUES(last_failure_at),
                 blacklisted_until = VALUES(blacklisted_until),
                 last_error_message = VALUES(last_error_message),
                 updated_at = VALUES(updated_at)',
                $table,
            ),
            [
                'connection_id' => $connectionId,
                'recent_failure_count' => $recentFailureCount,
                'window_started_at' => $this->toDateTime($windowStartedAt),
                'last_failure_at' => $this->toDateTime($lastFailureAt),
                'blacklisted_until' => $this->toDateTime($blacklistedUntil),
                'last_error_message' => $lastErrorMessage,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    /**
     * @since 0.1.0
     */
    private function ensureStateRowExists(int $connectionId): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s (connection_id, recent_failure_count, created_at, updated_at)
                 VALUES (:connection_id, 0, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE connection_id = connection_id',
                $this->tableNameResolver->resolve('connection_circuit_breakers'),
            ),
            [
                'connection_id' => $connectionId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    /**
     * @return array<string, int|string|null>
     *
     * @since 0.1.0
     */
    private function fetchStateForUpdate(int $connectionId, int $windowSeconds): array
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE connection_id = :connection_id LIMIT 1 FOR UPDATE', $this->tableNameResolver->resolve('connection_circuit_breakers')),
            ['connection_id' => $connectionId],
        );

        return $this->normalizeState(is_array($row) ? $row : [], $windowSeconds);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int|string|null>
     *
     * @since 0.1.0
     */
    private function normalizeState(array $row, int $windowSeconds): array
    {
        $now = time();
        $windowStartedAt = $this->toTimestamp($row['window_started_at'] ?? null);
        $blacklistedUntil = $this->toTimestamp($row['blacklisted_until'] ?? null);

        if ($windowStartedAt === null || $windowStartedAt <= ($now - $windowSeconds)) {
            $recentFailureCount = 0;
            $windowStartedAt = null;
        } else {
            $recentFailureCount = max(0, (int) ($row['recent_failure_count'] ?? 0));
        }

        if ($blacklistedUntil !== null && $blacklistedUntil <= $now) {
            $blacklistedUntil = null;
        }

        return [
            'recent_failure_count' => $recentFailureCount,
            'window_started_at' => $windowStartedAt,
            'last_failure_at' => $this->toTimestamp($row['last_failure_at'] ?? null),
            'blacklisted_until' => $blacklistedUntil,
            'last_error_message' => isset($row['last_error_message']) ? (string) $row['last_error_message'] : null,
        ];
    }

    /**
     * @since 0.1.0
     */
    private function toDateTime(?int $timestamp): ?string
    {
        return $timestamp === null ? null : gmdate('Y-m-d H:i:s', $timestamp);
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

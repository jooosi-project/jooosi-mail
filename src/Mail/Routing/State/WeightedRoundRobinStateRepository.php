<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing\State;

use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use RuntimeException;
use Throwable;
/**
 * Persists smooth weighted round robin state for routing scopes.
 *
 * @since 0.1.0
 */
#[Service]
final class WeightedRoundRobinStateRepository
{
    /**
     * @var string
     */
    private const GLOBAL_SCOPE = 'global';
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver)
    {
    }
    /**
     * @template TResult
     *
     * @param callable(array<int, int>): array{state: array<int, int>, result: TResult} $updater
     *
     * @return TResult
     *
     * @since 0.1.0
     */
    public function update(callable $updater): mixed
    {
        $this->connection->beginTransaction();
        try {
            $this->ensureStateRowExists();
            $row = $this->connection->fetchAssociative(sprintf('SELECT weights_json FROM %s WHERE scope_key = :scope_key LIMIT 1 FOR UPDATE', $this->tableNameResolver->resolve('weighted_round_robin_states')), ['scope_key' => self::GLOBAL_SCOPE]);
            if (!is_array($row)) {
                throw new RuntimeException('The weighted round robin routing state row could not be loaded.');
            }
            $updated = $updater($this->decodeState($row['weights_json'] ?? '{}'));
            $this->save($updated['state']);
            $this->connection->commit();
            return $updated['result'];
        } catch (Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }
    }
    /**
     * @param array<int, int> $state
     *
     * @since 0.1.0
     */
    private function save(array $state): void
    {
        $weightsJson = wp_json_encode($state);
        if (!is_string($weightsJson)) {
            throw new RuntimeException('The weighted round robin routing state could not be encoded as JSON.');
        }
        $this->connection->executeStatement(sprintf('UPDATE %s SET weights_json = :weights_json, updated_at = :updated_at WHERE scope_key = :scope_key', $this->tableNameResolver->resolve('weighted_round_robin_states')), ['scope_key' => self::GLOBAL_SCOPE, 'weights_json' => $weightsJson, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }
    /**
     * @return array<int, int>
     *
     * @since 0.1.0
     */
    private function decodeState(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, \true);
        if (!is_array($decoded)) {
            throw new RuntimeException('The weighted round robin routing state could not be decoded.');
        }
        $state = [];
        foreach ($decoded as $connectionId => $weight) {
            $state[(int) $connectionId] = (int) $weight;
        }
        return $state;
    }
    /**
     * @since 0.1.0
     */
    private function ensureStateRowExists(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->executeStatement(sprintf('INSERT INTO %s (scope_key, weights_json, created_at, updated_at)
                 VALUES (:scope_key, :weights_json, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE scope_key = scope_key', $this->tableNameResolver->resolve('weighted_round_robin_states')), ['scope_key' => self::GLOBAL_SCOPE, 'weights_json' => '{}', 'created_at' => $now, 'updated_at' => $now]);
    }
}

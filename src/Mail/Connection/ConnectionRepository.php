<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Connection;

use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Infrastructure\Security\SecretCipher;
/**
 * Repository for persisted mail connections.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionRepository
{
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver, private readonly SecretCipher $secretCipher)
    {
    }
    /**
     * @since 0.1.0
     */
    public function find(int $id): ?\JooosiMail\Mail\Connection\Connection
    {
        $row = $this->connection->fetchAssociative(sprintf('SELECT * FROM %s WHERE id = :id LIMIT 1', $this->tableNameResolver->resolve('connections')), ['id' => $id]);
        return is_array($row) ? $this->hydrate($row) : null;
    }
    /**
     * @return list<Connection>
     *
     * @since 0.1.0
     */
    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s ORDER BY is_enabled DESC, is_default DESC, priority ASC, id ASC', $this->tableNameResolver->resolve('connections')));
        return array_map(fn(array $row): \JooosiMail\Mail\Connection\Connection => $this->hydrate($row), $rows);
    }
    /**
     * @return list<Connection>
     *
     * @since 0.1.0
     */
    public function findActive(): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s WHERE is_enabled = 1 ORDER BY is_default DESC, priority ASC, id ASC', $this->tableNameResolver->resolve('connections')));
        return array_map(fn(array $row): \JooosiMail\Mail\Connection\Connection => $this->hydrate($row), $rows);
    }
    /**
     * @since 0.1.0
     */
    public function findDefault(): ?\JooosiMail\Mail\Connection\Connection
    {
        $row = $this->connection->fetchAssociative(sprintf('SELECT * FROM %s WHERE is_enabled = 1 AND is_default = 1 ORDER BY priority ASC, id ASC LIMIT 1', $this->tableNameResolver->resolve('connections')));
        return is_array($row) ? $this->hydrate($row) : null;
    }
    /**
     * @since 0.1.0
     */
    public function save(\JooosiMail\Mail\Connection\Connection $connection): int
    {
        $payload = ['profile_key' => $connection->profileKey, 'name' => $connection->name, 'dsn' => $connection->dsn, 'settings_json' => wp_json_encode($connection->settings), 'secrets_json' => wp_json_encode($this->encryptSecrets($connection->secrets)), 'is_enabled' => $connection->enabled ? 1 : 0, 'is_default' => $connection->default ? 1 : 0, 'priority' => $connection->priority, 'weight' => $connection->weight, 'webhook_enabled' => $connection->webhookEnabled ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')];
        $table = $this->tableNameResolver->resolve('connections');
        if ($connection->id !== null) {
            $this->connection->update($table, $payload, ['id' => $connection->id]);
            return $connection->id;
        }
        $payload['created_at'] = gmdate('Y-m-d H:i:s');
        $this->connection->insert($table, $payload);
        return (int) $this->connection->lastInsertId();
    }
    /**
     * @since 0.1.0
     */
    public function clearDefault(?int $exceptConnectionId = null): void
    {
        $table = $this->tableNameResolver->resolve('connections');
        $parameters = [];
        $sql = sprintf('UPDATE %s SET is_default = 0, updated_at = :updated_at WHERE is_default = 1', $table);
        $parameters['updated_at'] = gmdate('Y-m-d H:i:s');
        if ($exceptConnectionId !== null) {
            $sql .= ' AND id <> :except_connection_id';
            $parameters['except_connection_id'] = $exceptConnectionId;
        }
        $this->connection->executeStatement($sql, $parameters);
    }
    /**
     * @since 0.1.0
     */
    public function delete(int $connectionId): void
    {
        $this->connection->delete($this->tableNameResolver->resolve('connections'), ['id' => $connectionId]);
    }
    /**
     * @since 0.1.0
     */
    private function hydrate(array $row): \JooosiMail\Mail\Connection\Connection
    {
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), \true);
        $secrets = json_decode((string) ($row['secrets_json'] ?? '{}'), \true);
        return new \JooosiMail\Mail\Connection\Connection(id: isset($row['id']) ? (int) $row['id'] : null, profileKey: (string) ($row['profile_key'] ?? ''), name: (string) ($row['name'] ?? ''), dsn: isset($row['dsn']) && (string) $row['dsn'] !== '' ? (string) $row['dsn'] : null, settings: is_array($settings) ? $settings : [], secrets: $this->decryptSecrets(is_array($secrets) ? $secrets : []), enabled: (bool) ($row['is_enabled'] ?? \false), default: (bool) ($row['is_default'] ?? \false), priority: (int) ($row['priority'] ?? 10), weight: (int) ($row['weight'] ?? 1), webhookEnabled: (bool) ($row['webhook_enabled'] ?? \false));
    }
    /**
     * @param array<string, mixed> $secrets
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function encryptSecrets(array $secrets): array
    {
        $encrypted = [];
        foreach ($secrets as $key => $value) {
            if (is_array($value)) {
                $encrypted[$key] = $this->encryptSecrets($value);
                continue;
            }
            $encrypted[$key] = $this->secretCipher->encrypt((string) $value);
        }
        return $encrypted;
    }
    /**
     * @param array<string, mixed> $secrets
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function decryptSecrets(array $secrets): array
    {
        $decrypted = [];
        foreach ($secrets as $key => $value) {
            if (is_array($value)) {
                $decrypted[$key] = $this->decryptSecrets($value);
                continue;
            }
            $decrypted[$key] = $this->secretCipher->decrypt((string) $value);
        }
        return $decrypted;
    }
}

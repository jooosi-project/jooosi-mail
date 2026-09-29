<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMailDeps\Doctrine\DBAL\Schema\Table;
use JooosiMailDeps\Doctrine\DBAL\Types\Types;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use RuntimeException;
use Throwable;
/**
 * Persists migration execution history.
 *
 * @since 0.1.0
 */
#[Service]
final class MigrationRepository
{
    /**
     * @var string
     */
    private const TABLE_SUFFIX = 'migrations';
    public function __construct(private readonly Connection $connection, private readonly TableNameResolver $tableNameResolver)
    {
        $this->ensureTableExists();
    }
    /**
     * @return array<string>
     *
     * @since 0.1.0
     */
    public function executedVersions(): array
    {
        $versions = $this->connection->fetchFirstColumn(sprintf('SELECT version FROM %s ORDER BY version ASC', $this->tableName()));
        return array_values(array_map(static fn(mixed $version): string => (string) $version, $versions));
    }
    /**
     * @return array<string, MigrationExecution>
     *
     * @since 0.1.0
     */
    public function executionMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT version, class_name, description, executed_at, execution_time_ms FROM %s ORDER BY version ASC', $this->tableName()));
        $executions = [];
        foreach ($rows as $row) {
            $execution = new \JooosiMail\Database\Migration\MigrationExecution(version: (string) ($row['version'] ?? ''), className: (string) ($row['class_name'] ?? ''), description: (string) ($row['description'] ?? ''), executedAt: (string) ($row['executed_at'] ?? ''), executionTimeMs: (int) ($row['execution_time_ms'] ?? 0));
            $executions[$execution->version] = $execution;
        }
        return $executions;
    }
    /**
     * @since 0.1.0
     */
    public function recordExecution(\JooosiMail\Database\Migration\MigrationDefinition $definition, int $executionTimeMs): void
    {
        $this->insertExecution($definition, max(0, $executionTimeMs));
    }
    /**
     * @since 0.1.0
     */
    public function removeExecution(string $version): void
    {
        $this->connection->delete($this->tableName(), ['version' => $version]);
    }
    /**
     * @since 0.1.0
     */
    public function tableName(): string
    {
        return $this->tableNameResolver->resolve(self::TABLE_SUFFIX);
    }
    /**
     * @since 0.1.0
     */
    private function ensureTableExists(): void
    {
        $table = new Table($this->tableName());
        $table->addColumn('version', Types::STRING, ['length' => 32, 'notnull' => \true]);
        $table->addColumn('class_name', Types::STRING, ['length' => 255, 'notnull' => \true]);
        $table->addColumn('description', Types::TEXT, ['notnull' => \true]);
        $table->addColumn('executed_at', Types::DATETIME_MUTABLE, ['notnull' => \true]);
        $table->addColumn('execution_time_ms', Types::INTEGER, ['unsigned' => \true, 'notnull' => \true, 'default' => 0]);
        $table->setPrimaryKey(['version']);
        $table->addIndex(['executed_at'], 'idx_executed_at');
        \JooosiMail\Database\Migration\MigrationSchema::createTable($this->connection, $table);
    }
    /**
     * @since 0.1.0
     */
    private function insertExecution(\JooosiMail\Database\Migration\MigrationDefinition $definition, int $executionTimeMs): void
    {
        try {
            $this->connection->insert($this->tableName(), ['version' => $definition->version, 'class_name' => $definition->className, 'description' => $definition->description, 'executed_at' => $this->timestamp(), 'execution_time_ms' => $executionTimeMs]);
        } catch (Throwable $throwable) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to record Jooosi Mail migration "%s".', $definition->version), 0, $throwable);
        }
    }
    /**
     * @since 0.1.0
     */
    private function timestamp(): string
    {
        return function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s');
    }
}

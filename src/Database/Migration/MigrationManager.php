<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use RuntimeException;
use Throwable;
/**
 * Coordinates migration discovery, execution, rollback, and status reporting.
 *
 * @since 0.1.0
 */
#[Service]
final class MigrationManager
{
    public function __construct(private readonly Connection $connection, private readonly \JooosiMail\Database\Migration\MigrationRegistry $migrationRegistry, private readonly \JooosiMail\Database\Migration\MigrationRepository $migrationRepository, private readonly TableNameResolver $tableNameResolver)
    {
    }
    /**
     * @return array{
     *   migration_table: string,
     *   migrations_directory: string,
     *   total: int,
     *   executed: int,
     *   executed_unavailable: int,
     *   pending: int,
     *   previous_version: string,
     *   current_version: string,
     *   next_version: string,
     *   latest_version: string,
     *   migrations: list<array{version: string, class_name: string, description: string, status: string, executed_at: ?string, execution_time_ms: ?int}>
     * }
     *
     * @since 0.1.0
     */
    public function status(): array
    {
        $definitions = $this->migrationRegistry->all();
        $executionMap = $this->executionMap();
        $migrations = [];
        $knownVersions = [];
        $executedAvailable = 0;
        foreach ($definitions as $definition) {
            $knownVersions[$this->executionKey($definition->version)] = \true;
            $execution = $executionMap[$this->executionKey($definition->version)] ?? null;
            if ($execution instanceof \JooosiMail\Database\Migration\MigrationExecution) {
                ++$executedAvailable;
            }
            $migrations[] = ['version' => $definition->version, 'class_name' => $definition->className, 'description' => $definition->description, 'status' => $execution instanceof \JooosiMail\Database\Migration\MigrationExecution ? 'executed' : 'pending', 'executed_at' => $execution?->executedAt, 'execution_time_ms' => $execution?->executionTimeMs];
        }
        $executedUnavailable = 0;
        foreach ($executionMap as $version => $execution) {
            if (isset($knownVersions[$version])) {
                continue;
            }
            ++$executedUnavailable;
            $migrations[] = ['version' => $execution->version, 'class_name' => $execution->className, 'description' => $execution->description, 'status' => 'executed_unavailable', 'executed_at' => $execution->executedAt, 'execution_time_ms' => $execution->executionTimeMs];
        }
        usort($migrations, static fn(array $left, array $right): int => strcmp((string) $left['version'], (string) $right['version']));
        $executedVersions = array_values(array_filter(array_map(static fn(array $migration): string => (string) $migration['version'], $migrations), fn(string $version): bool => $version !== '' && isset($executionMap[$this->executionKey($version)])));
        $pendingVersions = array_values(array_map(static fn(\JooosiMail\Database\Migration\MigrationDefinition $definition): string => $definition->version, $this->migrationRegistry->pending($this->executedVersionsFromMap($executionMap))));
        $currentVersion = $executedVersions === [] ? '0' : (string) end($executedVersions);
        $previousVersion = count($executedVersions) < 2 ? '0' : (string) $executedVersions[count($executedVersions) - 2];
        $latestVersion = $definitions === [] ? '0' : $definitions[count($definitions) - 1]->version;
        $nextVersion = $pendingVersions === [] ? 'up_to_date' : $pendingVersions[0];
        return ['migration_table' => $this->migrationRepository->tableName(), 'migrations_directory' => $this->migrationRegistry->directory(), 'total' => count($definitions), 'executed' => $executedAvailable, 'executed_unavailable' => $executedUnavailable, 'pending' => count($definitions) - $executedAvailable, 'previous_version' => $previousVersion, 'current_version' => $currentVersion, 'next_version' => $nextVersion, 'latest_version' => $latestVersion, 'migrations' => $migrations];
    }
    /**
     * @return list<array{version: string, class_name: string, description: string, status: string, executed_at: ?string, execution_time_ms: ?int}>
     *
     * @since 0.1.0
     */
    public function list(): array
    {
        return $this->status()['migrations'];
    }
    /**
     * @return list<MigrationDefinition>
     *
     * @since 0.1.0
     */
    public function pending(): array
    {
        return $this->migrationRegistry->pending($this->executedVersions());
    }
    /**
     * @since 0.1.0
     */
    public function hasPendingMigrations(): bool
    {
        return $this->pending() !== [];
    }
    /**
     * @since 0.1.0
     */
    public function hasExecutedMigrations(): bool
    {
        return $this->executionMap() !== [];
    }
    /**
     * @param array<string> $versions
     *
     * @return array{executed: list<array{version: string, class_name: string, description: string, status: string, execution_time_ms: ?int}>, message: string, failed?: array{version: string, class_name: string, description: string, status: string, error: string}}
     *
     * @since 0.1.0
     */
    public function run(array $versions = [], bool $dryRun = \false): array
    {
        $definitions = $versions === [] ? $this->pending() : $this->resolveRequestedDefinitions($versions);
        if ($definitions === []) {
            return ['executed' => [], 'message' => 'No migrations to run.'];
        }
        $executed = [];
        foreach ($definitions as $definition) {
            try {
                $executed[] = $this->runSingle($definition, $dryRun);
            } catch (Throwable $throwable) {
                return ['executed' => $executed, 'failed' => ['version' => $definition->version, 'class_name' => $definition->className, 'description' => $definition->description, 'status' => 'failed', 'error' => $throwable->getMessage()], 'message' => sprintf('Migration %s failed: %s', $definition->version, esc_html($throwable->getMessage()))];
            }
        }
        return ['executed' => $executed, 'message' => $dryRun ? sprintf('%d migration(s) would be executed.', count($executed)) : sprintf('%d migration(s) executed successfully.', count($executed))];
    }
    /**
     * @return array{rolled_back: list<array{version: string, class_name: string, description: string, status: string, execution_time_ms: ?int}>, message: string, failed?: array{version: string, class_name: string, description: string, status: string, error: string}}
     *
     * @since 0.1.0
     */
    public function rollback(int $steps = 1, ?string $toVersion = null, bool $dryRun = \false): array
    {
        $definitions = $this->rollbackDefinitions(max(1, $steps), $toVersion);
        if ($definitions === []) {
            return ['rolled_back' => [], 'message' => 'No migrations to rollback.'];
        }
        $rolledBack = [];
        foreach ($definitions as $definition) {
            try {
                $rolledBack[] = $this->rollbackSingle($definition, $dryRun);
            } catch (Throwable $throwable) {
                return ['rolled_back' => $rolledBack, 'failed' => ['version' => $definition->version, 'class_name' => $definition->className, 'description' => $definition->description, 'status' => 'failed', 'error' => $throwable->getMessage()], 'message' => sprintf('Rollback for migration %s failed: %s', $definition->version, esc_html($throwable->getMessage()))];
            }
        }
        return ['rolled_back' => $rolledBack, 'message' => $dryRun ? sprintf('%d migration(s) would be rolled back.', count($rolledBack)) : sprintf('%d migration(s) rolled back successfully.', count($rolledBack))];
    }
    /**
     * @return array{rolled_back: list<array{version: string, class_name: string, description: string, status: string, execution_time_ms: ?int}>, message: string, failed?: array{version: string, class_name: string, description: string, status: string, error: string}}
     *
     * @since 0.1.0
     */
    public function reset(bool $dryRun = \false): array
    {
        $definitions = $this->rollbackDefinitions(\PHP_INT_MAX, null);
        if ($definitions === []) {
            return ['rolled_back' => [], 'message' => 'No migrations to rollback.'];
        }
        return $this->rollback(count($definitions), null, $dryRun);
    }
    /**
     * @return list<MigrationDefinition>
     *
     * @since 0.1.0
     */
    private function resolveRequestedDefinitions(array $versions): array
    {
        $definitions = [];
        $knownVersions = [];
        $executedVersions = $this->executedVersions();
        foreach ($versions as $version) {
            $definition = $this->migrationRegistry->find($version);
            if (!$definition instanceof \JooosiMail\Database\Migration\MigrationDefinition) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new RuntimeException(sprintf('The Jooosi Mail migration "%s" could not be found.', $version));
            }
            if (in_array($definition->version, $executedVersions, \true)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new RuntimeException(sprintf('The Jooosi Mail migration "%s" has already been executed.', $definition->version));
            }
            if (isset($knownVersions[$definition->version])) {
                continue;
            }
            $knownVersions[$definition->version] = \true;
            $definitions[] = $definition;
        }
        usort($definitions, static fn(\JooosiMail\Database\Migration\MigrationDefinition $left, \JooosiMail\Database\Migration\MigrationDefinition $right): int => strcmp($left->version, $right->version));
        return $definitions;
    }
    /**
     * @return array{version: string, class_name: string, description: string, status: string, execution_time_ms: ?int}
     *
     * @since 0.1.0
     */
    private function runSingle(\JooosiMail\Database\Migration\MigrationDefinition $definition, bool $dryRun): array
    {
        if ($dryRun) {
            return ['version' => $definition->version, 'class_name' => $definition->className, 'description' => $definition->description, 'status' => 'planned', 'execution_time_ms' => null];
        }
        $migration = $this->migrationRegistry->createInstance($definition);
        $startedAt = microtime(\true);
        $migration->up($this->connection, $this->tableNameResolver);
        $executionTimeMs = (int) round((microtime(\true) - $startedAt) * 1000);
        $this->migrationRepository->recordExecution($definition, $executionTimeMs);
        return ['version' => $definition->version, 'class_name' => $definition->className, 'description' => $definition->description, 'status' => 'executed', 'execution_time_ms' => $executionTimeMs];
    }
    /**
     * @return array{version: string, class_name: string, description: string, status: string, execution_time_ms: ?int}
     *
     * @since 0.1.0
     */
    private function rollbackSingle(\JooosiMail\Database\Migration\MigrationDefinition $definition, bool $dryRun): array
    {
        if ($dryRun) {
            return ['version' => $definition->version, 'class_name' => $definition->className, 'description' => $definition->description, 'status' => 'planned', 'execution_time_ms' => null];
        }
        $migration = $this->migrationRegistry->createInstance($definition);
        $startedAt = microtime(\true);
        $migration->down($this->connection, $this->tableNameResolver);
        $executionTimeMs = (int) round((microtime(\true) - $startedAt) * 1000);
        $this->migrationRepository->removeExecution($definition->version);
        return ['version' => $definition->version, 'class_name' => $definition->className, 'description' => $definition->description, 'status' => 'rolled_back', 'execution_time_ms' => $executionTimeMs];
    }
    /**
     * @return list<MigrationDefinition>
     *
     * @since 0.1.0
     */
    private function rollbackDefinitions(int $steps, ?string $toVersion): array
    {
        $executionMap = $this->executionMap();
        $executedVersions = $this->executedVersionsFromMap($executionMap);
        if ($executedVersions === []) {
            return [];
        }
        if ($toVersion !== null && $toVersion !== '' && $toVersion !== '0' && !isset($executionMap[$this->executionKey($toVersion)]) && !$this->migrationRegistry->find($toVersion) instanceof \JooosiMail\Database\Migration\MigrationDefinition) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('The Jooosi Mail migration "%s" could not be found.', $toVersion));
        }
        rsort($executedVersions, \SORT_STRING);
        $definitions = [];
        foreach ($executedVersions as $version) {
            if ($toVersion !== null && strcmp($version, $toVersion) <= 0) {
                continue;
            }
            $definition = $this->migrationRegistry->find($version);
            if (!$definition instanceof \JooosiMail\Database\Migration\MigrationDefinition) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new RuntimeException(sprintf('The executed migration "%s" is not available for rollback.', $version));
            }
            $definitions[] = $definition;
            if ($toVersion === null && count($definitions) >= $steps) {
                break;
            }
        }
        return $definitions;
    }
    /**
     * @return array<string, MigrationExecution>
     *
     * @since 0.1.0
     */
    private function executionMap(): array
    {
        $executions = [];
        foreach ($this->migrationRepository->executionMap() as $execution) {
            $executions[$this->executionKey($execution->version)] = $execution;
        }
        return $executions;
    }
    /**
     * @return array<string>
     *
     * @since 0.1.0
     */
    private function executedVersions(): array
    {
        return $this->executedVersionsFromMap($this->executionMap());
    }
    /**
     * @param array<string, MigrationExecution> $executionMap
     *
     * @return array<string>
     *
     * @since 0.1.0
     */
    private function executedVersionsFromMap(array $executionMap): array
    {
        return array_values(array_map(static fn(\JooosiMail\Database\Migration\MigrationExecution $execution): string => $execution->version, $executionMap));
    }
    /**
     * @since 0.1.0
     */
    private function executionKey(string $version): string
    {
        return 'version:' . $version;
    }
}

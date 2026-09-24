<?php

declare(strict_types=1);

namespace JooosiMail\Cli\Presentation;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Projects migration application results into stable WP-CLI rows and lines.
 *
 * @since 1.0.9
 */
#[Service]
final class MigrationCliPresenter
{
    /**
     * @return list<string>
     *
     * @since 1.0.9
     */
    public function statusFields(): array
    {
        return ['metric', 'value'];
    }

    /**
     * @return list<string>
     *
     * @since 1.0.9
     */
    public function listFields(): array
    {
        return ['version', 'class_name', 'description', 'status', 'executed_at', 'execution_time_ms'];
    }

    /**
     * @return list<string>
     *
     * @since 1.0.9
     */
    public function executionFields(): array
    {
        return ['version', 'class_name', 'description', 'status', 'execution_time_ms'];
    }

    /**
     * @return list<string>
     *
     * @since 1.0.9
     */
    public function failureFields(): array
    {
        return ['version', 'class_name', 'description', 'status', 'error'];
    }

    /**
     * @param array<string, mixed> $status
     *
     * @return list<array{metric: string, value: string}>
     *
     * @since 1.0.9
     */
    public function statusRows(array $status): array
    {
        return [
            ['metric' => 'migration_table', 'value' => (string) $status['migration_table']],
            ['metric' => 'migrations_directory', 'value' => (string) $status['migrations_directory']],
            ['metric' => 'previous_version', 'value' => (string) $status['previous_version']],
            ['metric' => 'current_version', 'value' => (string) $status['current_version']],
            ['metric' => 'next_version', 'value' => (string) $status['next_version']],
            ['metric' => 'latest_version', 'value' => (string) $status['latest_version']],
            ['metric' => 'available_migrations', 'value' => (string) $status['total']],
            ['metric' => 'executed_migrations', 'value' => (string) $status['executed']],
            ['metric' => 'executed_unavailable_migrations', 'value' => (string) $status['executed_unavailable']],
            ['metric' => 'pending_migrations', 'value' => (string) $status['pending']],
        ];
    }

    /**
     * @param list<array<string, mixed>> $migrations
     *
     * @return list<array{version: string, class_name: string, description: string, status: string, executed_at: string, execution_time_ms: string}>
     *
     * @since 1.0.9
     */
    public function listRows(array $migrations): array
    {
        return array_map(
            static fn (array $migration): array => [
                'version' => (string) $migration['version'],
                'class_name' => (string) $migration['class_name'],
                'description' => (string) $migration['description'],
                'status' => (string) $migration['status'],
                'executed_at' => (string) ($migration['executed_at'] ?? ''),
                'execution_time_ms' => $migration['execution_time_ms'] === null ? '' : (string) $migration['execution_time_ms'],
            ],
            $migrations,
        );
    }

    /**
     * @param list<array<string, mixed>> $migrations
     *
     * @return list<array{version: string, class_name: string, description: string, status: string, execution_time_ms: string}>
     *
     * @since 1.0.9
     */
    public function executionRows(array $migrations): array
    {
        return array_map(
            static fn (array $migration): array => [
                'version' => (string) $migration['version'],
                'class_name' => (string) $migration['class_name'],
                'description' => (string) $migration['description'],
                'status' => (string) $migration['status'],
                'execution_time_ms' => $migration['execution_time_ms'] === null ? '' : (string) $migration['execution_time_ms'],
            ],
            $migrations,
        );
    }

    /**
     * @param array<string, mixed> $failed
     *
     * @return list<array{version: string, class_name: string, description: string, status: string, error: string}>
     *
     * @since 1.0.9
     */
    public function failureRows(array $failed): array
    {
        return [[
            'version' => (string) $failed['version'],
            'class_name' => (string) $failed['class_name'],
            'description' => (string) $failed['description'],
            'status' => (string) $failed['status'],
            'error' => (string) $failed['error'],
        ]];
    }

    /**
     * @param array{path: string, className: string, version: string, description: string} $migration
     *
     * @return list<string>
     *
     * @since 1.0.9
     */
    public function createdMigrationLines(array $migration): array
    {
        return [
            '',
            'File: ' . $migration['path'],
            'Class: ' . $migration['className'],
            'Version: ' . $migration['version'],
            'Description: ' . $migration['description'],
            '',
            'Next steps:',
            '  1. Edit the migration file to implement up() and down().',
            "  2. Run 'wp jooosi-mail migration:run' when the migration is ready.",
        ];
    }
}

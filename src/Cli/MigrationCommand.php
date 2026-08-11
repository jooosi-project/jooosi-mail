<?php

declare(strict_types=1);

namespace JooosiMail\Cli;

use InvalidArgumentException;
use JooosiMail\Database\Migration\MigrationManager;
use JooosiMail\Database\Migration\MigrationStubGenerator;
use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Service;
use RuntimeException;
use WP_CLI;

use function WP_CLI\Utils\format_items;

/**
 * Manage Jooosi Mail schema migrations from WP-CLI.
 *
 * @since 0.1.0
 */
#[Service]
final class MigrationCommand
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly MigrationManager $migrationManager,
        private readonly MigrationStubGenerator $migrationStubGenerator,
    ) {
    }

    /**
     * Generate a Jooosi Mail migration stub.
     *
     * ## OPTIONS
     *
     * <name>
     * : Migration name used to build the class suffix, such as `create_mail_events_table`.
     *
     * ## EXAMPLES
     *
     *     $ wp jooosi-mail migration:make create_delivery_reports_table
     *     Success: Created migration JooosiMail\Database\Migration\Versions\Version202603230001CreateDeliveryReportsTable at /path/to/wp-content/plugins/jooosi-mail/src/Database/Migration/Versions/Version202603230001CreateDeliveryReportsTable.php.
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(
        name: 'jooosi-mail migration:make',
        description: 'Generate a Jooosi Mail migration stub.',
        aliases: ['jooosi-mail migration:create'],
    )]
    public function make(array $args, array $assocArgs): void
    {
        $name = sanitize_text_field((string) ($args[0] ?? ''));
        if ($name === '') {
            WP_CLI::error('Provide the migration name as the first argument.');

            return;
        }

        try {
            $migration = $this->migrationStubGenerator->generate($name);

            $this->renderCreatedMigration($migration);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());
        }
    }

    /**
     * Show migration status summary.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp jooosi-mail migration:status
     *     $ wp jooosi-mail migration:status --format=json
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(name: 'jooosi-mail migration:status', description: 'Show Jooosi Mail migration status.')]
    public function status(array $args, array $assocArgs): void
    {
        $format = $this->resolveFormat($assocArgs);

        try {
            $status = $this->migrationManager->status();
        } catch (RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());

            return;
        }

        $items = [
            ['metric' => 'migration_table', 'value' => $status['migration_table']],
            ['metric' => 'migrations_directory', 'value' => $status['migrations_directory']],
            ['metric' => 'previous_version', 'value' => $status['previous_version']],
            ['metric' => 'current_version', 'value' => $status['current_version']],
            ['metric' => 'next_version', 'value' => $status['next_version']],
            ['metric' => 'latest_version', 'value' => $status['latest_version']],
            ['metric' => 'available_migrations', 'value' => (string) $status['total']],
            ['metric' => 'executed_migrations', 'value' => (string) $status['executed']],
            ['metric' => 'executed_unavailable_migrations', 'value' => (string) $status['executed_unavailable']],
            ['metric' => 'pending_migrations', 'value' => (string) $status['pending']],
        ];

        format_items($format, $items, ['metric', 'value']);

        if ($format !== 'table') {
            return;
        }

        if ($status['pending'] > 0) {
            WP_CLI::warning(sprintf('%d migration(s) are pending.', $status['pending']));

            return;
        }

        WP_CLI::success('All discovered migrations are up to date.');
    }

    /**
     * List discovered migrations and their execution state.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter migrations by status.
     * ---
     * default: all
     * options:
     *   - all
     *   - executed
     *   - pending
     *   - executed_unavailable
     * ---
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp jooosi-mail migration:list
     *     $ wp jooosi-mail migration:list --status=pending
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(name: 'jooosi-mail migration:list', description: 'List Jooosi Mail migrations.')]
    public function listing(array $args, array $assocArgs): void
    {
        $format = $this->resolveFormat($assocArgs);
        $statusFilter = $this->resolveStatusFilter($assocArgs);

        try {
            $migrations = $this->migrationManager->list();
        } catch (RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());

            return;
        }

        if ($statusFilter !== 'all') {
            $migrations = array_values(array_filter(
                $migrations,
                static fn (array $migration): bool => $migration['status'] === $statusFilter,
            ));
        }

        if ($migrations === []) {
            if ($format === 'table') {
                WP_CLI::warning('No migrations matched the requested filter.');

                return;
            }

            format_items($format, [], ['version', 'class_name', 'description', 'status', 'executed_at', 'execution_time_ms']);

            return;
        }

        $items = array_map(
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

        format_items($format, $items, ['version', 'class_name', 'description', 'status', 'executed_at', 'execution_time_ms']);
    }

    /**
     * Run pending migrations or explicitly requested versions.
     *
     * ## OPTIONS
     *
     * [<version>...]
     * : Specific migration versions to execute.
     *
     * [--dry-run]
     * : Show what would be executed without applying changes.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp jooosi-mail migration:run
     *     $ wp jooosi-mail migration:run --dry-run
     *     $ wp jooosi-mail migration:run 202603230001
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(name: 'jooosi-mail migration:run', description: 'Run Jooosi Mail migrations.')]
    public function run(array $args, array $assocArgs): void
    {
        $format = $this->resolveFormat($assocArgs);
        $versions = $this->sanitizeVersions($args);
        $dryRun = $this->hasFlag($assocArgs, 'dry-run');
        $yes = $this->hasFlag($assocArgs, 'yes');

        try {
            if (! $dryRun && ! $yes && $format === 'table') {
                $count = $versions === [] ? count($this->migrationManager->pending()) : count($versions);
                if ($count > 0) {
                    WP_CLI::confirm(sprintf('Run %d migration(s)?', $count));
                }
            }

            $result = $this->migrationManager->run($versions, $dryRun);
        } catch (RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());

            return;
        }

        $items = array_map(
            static fn (array $migration): array => [
                'version' => (string) $migration['version'],
                'class_name' => (string) $migration['class_name'],
                'description' => (string) $migration['description'],
                'status' => (string) $migration['status'],
                'execution_time_ms' => $migration['execution_time_ms'] === null ? '' : (string) $migration['execution_time_ms'],
            ],
            $result['executed'],
        );

        if ($items !== []) {
            format_items($format, $items, ['version', 'class_name', 'description', 'status', 'execution_time_ms']);
        } elseif ($format !== 'table') {
            format_items($format, [], ['version', 'class_name', 'description', 'status', 'execution_time_ms']);
        }

        if (isset($result['failed'])) {
            $failed = [[
                'version' => (string) $result['failed']['version'],
                'class_name' => (string) $result['failed']['class_name'],
                'description' => (string) $result['failed']['description'],
                'status' => (string) $result['failed']['status'],
                'error' => (string) $result['failed']['error'],
            ]];

            if ($format === 'table') {
                WP_CLI::line('');
            }

            format_items($format, $failed, ['version', 'class_name', 'description', 'status', 'error']);
            WP_CLI::error($result['message']);

            return;
        }

        if ($format === 'table') {
            WP_CLI::success($result['message']);
        }
    }

    /**
     * Roll back executed migrations.
     *
     * ## OPTIONS
     *
     * [--steps=<steps>]
     * : Number of migrations to roll back.
     * ---
     * default: 1
     * ---
     *
     * [--to=<version>]
     * : Roll back every migration newer than the specified version.
     *
     * [--reset]
     * : Roll back every executed migration.
     *
     * [--dry-run]
     * : Show what would be rolled back without applying changes.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp jooosi-mail migration:rollback
     *     $ wp jooosi-mail migration:rollback --steps=2
     *     $ wp jooosi-mail migration:rollback --to=202603230001
     *     $ wp jooosi-mail migration:rollback --reset --yes
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(name: 'jooosi-mail migration:rollback', description: 'Rollback Jooosi Mail migrations.')]
    public function rollback(array $args, array $assocArgs): void
    {
        $format = $this->resolveFormat($assocArgs);
        $steps = max(1, (int) ($assocArgs['steps'] ?? 1));
        $toVersion = isset($assocArgs['to']) ? sanitize_text_field((string) $assocArgs['to']) : null;
        $reset = $this->hasFlag($assocArgs, 'reset');
        $dryRun = $this->hasFlag($assocArgs, 'dry-run');
        $yes = $this->hasFlag($assocArgs, 'yes');

        try {
            if (! $dryRun && ! $yes && $format === 'table' && $this->migrationManager->hasExecutedMigrations()) {
                if ($reset) {
                    WP_CLI::confirm('Rollback all executed migrations?');
                } elseif ($toVersion !== null && $toVersion !== '') {
                    WP_CLI::confirm(sprintf('Rollback migrations newer than %s?', $toVersion));
                } else {
                    WP_CLI::confirm(sprintf('Rollback %d migration(s)?', $steps));
                }
            }

            $result = $reset
                ? $this->migrationManager->reset($dryRun)
                : $this->migrationManager->rollback($steps, $toVersion, $dryRun);
        } catch (RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());

            return;
        }

        $items = array_map(
            static fn (array $migration): array => [
                'version' => (string) $migration['version'],
                'class_name' => (string) $migration['class_name'],
                'description' => (string) $migration['description'],
                'status' => (string) $migration['status'],
                'execution_time_ms' => $migration['execution_time_ms'] === null ? '' : (string) $migration['execution_time_ms'],
            ],
            $result['rolled_back'],
        );

        if ($items !== []) {
            format_items($format, $items, ['version', 'class_name', 'description', 'status', 'execution_time_ms']);
        } elseif ($format !== 'table') {
            format_items($format, [], ['version', 'class_name', 'description', 'status', 'execution_time_ms']);
        }

        if (isset($result['failed'])) {
            $failed = [[
                'version' => (string) $result['failed']['version'],
                'class_name' => (string) $result['failed']['class_name'],
                'description' => (string) $result['failed']['description'],
                'status' => (string) $result['failed']['status'],
                'error' => (string) $result['failed']['error'],
            ]];

            if ($format === 'table') {
                WP_CLI::line('');
            }

            format_items($format, $failed, ['version', 'class_name', 'description', 'status', 'error']);
            WP_CLI::error($result['message']);

            return;
        }

        if ($format === 'table') {
            WP_CLI::success($result['message']);
        }
    }

    /**
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    private function resolveFormat(array $assocArgs): string
    {
        $format = sanitize_text_field((string) ($assocArgs['format'] ?? 'table'));
        $supportedFormats = ['table', 'json', 'yaml', 'csv'];

        if (! in_array($format, $supportedFormats, true)) {
            WP_CLI::error(sprintf('Unsupported format "%s". Use one of: %s.', $format, implode(', ', $supportedFormats)));

            return 'table';
        }

        return $format;
    }

    /**
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    private function resolveStatusFilter(array $assocArgs): string
    {
        $status = sanitize_text_field((string) ($assocArgs['status'] ?? 'all'));
        $supportedStatuses = ['all', 'executed', 'pending', 'executed_unavailable'];

        if (! in_array($status, $supportedStatuses, true)) {
            WP_CLI::error(sprintf('Unsupported status filter "%s". Use one of: %s.', $status, implode(', ', $supportedStatuses)));

            return 'all';
        }

        return $status;
    }

    /**
     * @param array<int, string> $versions
     *
     * @return array<string>
     *
     * @since 0.1.0
     */
    private function sanitizeVersions(array $versions): array
    {
        return array_values(array_filter(array_map(
            static fn (string $version): string => sanitize_text_field($version),
            $versions,
        ), static fn (string $version): bool => $version !== ''));
    }

    /**
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    private function hasFlag(array $assocArgs, string $flag): bool
    {
        if (! array_key_exists($flag, $assocArgs)) {
            return false;
        }

        $value = $assocArgs[$flag];

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array{path: string, className: string, version: string, description: string} $migration
     *
     * @since 0.1.0
     */
    private function renderCreatedMigration(array $migration): void
    {
        WP_CLI::success('Migration created successfully.');
        WP_CLI::line('');
        WP_CLI::line('File: ' . $migration['path']);
        WP_CLI::line('Class: ' . $migration['className']);
        WP_CLI::line('Version: ' . $migration['version']);
        WP_CLI::line('Description: ' . $migration['description']);
        WP_CLI::line('');
        WP_CLI::line('Next steps:');
        WP_CLI::line('  1. Edit the migration file to implement up() and down().');
        WP_CLI::line("  2. Run 'wp jooosi-mail migration:run' when the migration is ready.");
    }
}

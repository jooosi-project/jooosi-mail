<?php

declare (strict_types=1);
namespace JooosiMail\Cli;

use InvalidArgumentException;
use JooosiMail\Cli\Application\CliBooleanParser;
use JooosiMail\Cli\Application\MigrationCommandApplicationService;
use JooosiMail\Cli\Application\MigrationInputNormalizer;
use JooosiMail\Cli\Application\MigrationQueryService;
use JooosiMail\Cli\Presentation\MigrationCliPresenter;
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
    private readonly MigrationCommandApplicationService $applicationService;
    private readonly MigrationQueryService $queryService;
    private readonly MigrationInputNormalizer $inputNormalizer;
    private readonly MigrationCliPresenter $presenter;
    /**
     * @since 0.1.0
     */
    public function __construct(MigrationManager $migrationManager, MigrationStubGenerator $migrationStubGenerator, ?MigrationCommandApplicationService $applicationService = null, ?MigrationQueryService $queryService = null, ?MigrationInputNormalizer $inputNormalizer = null, ?MigrationCliPresenter $presenter = null)
    {
        $this->applicationService = $applicationService ?? new MigrationCommandApplicationService($migrationManager, $migrationStubGenerator);
        $this->queryService = $queryService ?? new MigrationQueryService($migrationManager);
        $this->inputNormalizer = $inputNormalizer ?? new MigrationInputNormalizer(new CliBooleanParser());
        $this->presenter = $presenter ?? new MigrationCliPresenter();
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
    #[Command(name: 'jooosi-mail migration:make', description: 'Generate a Jooosi Mail migration stub.', aliases: ['jooosi-mail migration:create'])]
    public function make(array $args, array $assocArgs): void
    {
        $name = $this->inputNormalizer->name($args);
        if ($name === '') {
            WP_CLI::error('Provide the migration name as the first argument.');
            return;
        }
        try {
            $migration = $this->applicationService->make($name);
            $this->renderCreatedMigration($migration);
        } catch (InvalidArgumentException|RuntimeException $exception) {
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
            $status = $this->queryService->status();
        } catch (RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());
            return;
        }
        $items = $this->presenter->statusRows($status);
        format_items($format, $items, $this->presenter->statusFields());
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
            $migrations = $this->queryService->list();
        } catch (RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());
            return;
        }
        if ($statusFilter !== 'all') {
            $migrations = array_values(array_filter($migrations, static fn(array $migration): bool => $migration['status'] === $statusFilter));
        }
        if ($migrations === []) {
            if ($format === 'table') {
                WP_CLI::warning('No migrations matched the requested filter.');
                return;
            }
            format_items($format, [], $this->presenter->listFields());
            return;
        }
        $items = $this->presenter->listRows($migrations);
        format_items($format, $items, $this->presenter->listFields());
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
        $versions = $this->inputNormalizer->versions($args);
        $dryRun = $this->inputNormalizer->flag($assocArgs, 'dry-run');
        $yes = $this->inputNormalizer->flag($assocArgs, 'yes');
        try {
            if (!$dryRun && !$yes && $format === 'table') {
                $count = $versions === [] ? count($this->queryService->pending()) : count($versions);
                if ($count > 0) {
                    WP_CLI::confirm(sprintf('Run %d migration(s)?', $count));
                }
            }
            $result = $this->applicationService->run($versions, $dryRun);
        } catch (RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());
            return;
        }
        $items = $this->presenter->executionRows($result['executed']);
        if ($items !== []) {
            format_items($format, $items, $this->presenter->executionFields());
        } elseif ($format !== 'table') {
            format_items($format, [], $this->presenter->executionFields());
        }
        if (isset($result['failed'])) {
            $failed = $this->presenter->failureRows($result['failed']);
            if ($format === 'table') {
                WP_CLI::line('');
            }
            format_items($format, $failed, $this->presenter->failureFields());
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
        $steps = $this->inputNormalizer->steps($assocArgs);
        $toVersion = $this->inputNormalizer->toVersion($assocArgs);
        $reset = $this->inputNormalizer->flag($assocArgs, 'reset');
        $dryRun = $this->inputNormalizer->flag($assocArgs, 'dry-run');
        $yes = $this->inputNormalizer->flag($assocArgs, 'yes');
        try {
            if (!$dryRun && !$yes && $format === 'table' && $this->queryService->hasExecutedMigrations()) {
                if ($reset) {
                    WP_CLI::confirm('Rollback all executed migrations?');
                } elseif ($toVersion !== null && $toVersion !== '') {
                    WP_CLI::confirm(sprintf('Rollback migrations newer than %s?', $toVersion));
                } else {
                    WP_CLI::confirm(sprintf('Rollback %d migration(s)?', $steps));
                }
            }
            $result = $reset ? $this->applicationService->reset($dryRun) : $this->applicationService->rollback($steps, $toVersion, $dryRun);
        } catch (RuntimeException $exception) {
            WP_CLI::error($exception->getMessage());
            return;
        }
        $items = $this->presenter->executionRows($result['rolled_back']);
        if ($items !== []) {
            format_items($format, $items, $this->presenter->executionFields());
        } elseif ($format !== 'table') {
            format_items($format, [], $this->presenter->executionFields());
        }
        if (isset($result['failed'])) {
            $failed = $this->presenter->failureRows($result['failed']);
            if ($format === 'table') {
                WP_CLI::line('');
            }
            format_items($format, $failed, $this->presenter->failureFields());
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
        $format = $this->inputNormalizer->format($assocArgs);
        if (!$this->inputNormalizer->supportsFormat($format)) {
            WP_CLI::error(sprintf('Unsupported format "%s". Use one of: %s.', $format, implode(', ', $this->inputNormalizer->supportedFormats())));
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
        $status = $this->inputNormalizer->statusFilter($assocArgs);
        if (!$this->inputNormalizer->supportsStatusFilter($status)) {
            WP_CLI::error(sprintf('Unsupported status filter "%s". Use one of: %s.', $status, implode(', ', $this->inputNormalizer->supportedStatusFilters())));
            return 'all';
        }
        return $status;
    }
    /**
     * @param array{path: string, className: string, version: string, description: string} $migration
     *
     * @since 0.1.0
     */
    private function renderCreatedMigration(array $migration): void
    {
        WP_CLI::success('Migration created successfully.');
        foreach ($this->presenter->createdMigrationLines($migration) as $line) {
            WP_CLI::line($line);
        }
    }
}

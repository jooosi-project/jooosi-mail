<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Cli;

use JooosiMail\Cli\Application\MigrationInputNormalizer;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Covers migration WP-CLI command behavior.
 *
 * @since 0.1.0
 */
final class MigrationCommandTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function testMakeRendersGeneratedMigrationDetails(): void
    {
        $generatedPath = null;

        try {
            $output = $this->captureCli(function (): void {
                $this->migrationCommand()->make(['create_cli_characterization_table'], []);
            });

            foreach (preg_split('/\R/', $output['stdout']) ?: [] as $line) {
                if (str_starts_with($line, 'File: ')) {
                    $generatedPath = substr($line, 6);
                    break;
                }
            }

            self::assertStringContainsString('Success: Migration created successfully.', $output['stdout']);
            self::assertNotNull($generatedPath);
            self::assertFileExists($generatedPath);
            self::assertStringContainsString('Next steps:', $output['stdout']);
        } finally {
            if (is_string($generatedPath) && is_file($generatedPath)) {
                unlink($generatedPath);
            }
        }
    }

    /**
     * @since 0.1.0
     */
    public function testStatusRendersExecutedMigrationSummaryAsJson(): void
    {
        $output = $this->captureCli(function (): void {
            $this->migrationCommand()->status([], ['format' => 'json']);
        });
        $items = json_decode(trim($output['stdout']), true, flags: JSON_THROW_ON_ERROR);
        $summary = [];

        foreach ($items as $item) {
            $summary[(string) $item['metric']] = (string) $item['value'];
        }

        self::assertSame('', trim($output['stderr']));
        self::assertSame($summary['latest_version'], $summary['current_version']);
        self::assertSame('0', $summary['pending_migrations']);
        self::assertSame($summary['available_migrations'], $summary['executed_migrations']);
    }

    /**
     * @since 1.0.9
     */
    public function testListRendersExecutedMigrationRowsAsJson(): void
    {
        $output = $this->captureCli(function (): void {
            $this->migrationCommand()->listing([], [
                'status' => 'executed',
                'format' => 'json',
            ]);
        });
        $items = json_decode(trim($output['stdout']), true, flags: JSON_THROW_ON_ERROR);

        self::assertNotEmpty($items);
        self::assertSame(
            ['version', 'class_name', 'description', 'status', 'executed_at', 'execution_time_ms'],
            array_keys($items[0]),
        );

        foreach ($items as $item) {
            self::assertSame('executed', $item['status']);
        }
    }

    /**
     * @since 1.0.9
     */
    public function testMigrationInputNormalizerHandlesMalformedOptionsSafely(): void
    {
        $normalizer = $this->container()->get(MigrationInputNormalizer::class);
        self::assertInstanceOf(MigrationInputNormalizer::class, $normalizer);

        self::assertTrue($normalizer->flag(['dry-run' => 'on'], 'dry-run'));
        self::assertFalse($normalizer->flag(['dry-run' => 'maybe'], 'dry-run'));
        self::assertFalse($normalizer->flag(['dry-run' => '0'], 'dry-run'));

        self::assertSame('JSON', $normalizer->format(['format' => 'JSON']));
        self::assertFalse($normalizer->supportsFormat('JSON'));
        self::assertSame('unexpected', $normalizer->statusFilter(['status' => 'unexpected']));
        self::assertFalse($normalizer->supportsStatusFilter('unexpected'));
    }

    /**
     * @since 0.1.0
     */
    public function testRunExecutesPendingMigrationsAndRendersJsonRows(): void
    {
        $this->migrationManager()->reset();

        self::assertSame(0, $this->countRows('migrations'));

        $output = $this->captureCli(function (): void {
            $this->migrationCommand()->run([], [
                'yes' => true,
                'format' => 'json',
            ]);
        });
        $items = json_decode(trim($output['stdout']), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('', trim($output['stderr']));
        self::assertNotEmpty($items);
        self::assertSame(count($items), $this->countRows('migrations'));

        foreach ($items as $item) {
            self::assertSame('executed', $item['status']);
        }
    }

    /**
     * @since 0.1.0
     */
    public function testRollbackStepsRollsBackTheLatestMigrationOnly(): void
    {
        $output = $this->captureCli(function (): void {
            $this->migrationCommand()->rollback([], [
                'steps' => 1,
                'yes' => true,
                'format' => 'json',
            ]);
        });
        $items = json_decode(trim($output['stdout']), true, flags: JSON_THROW_ON_ERROR);
        $status = $this->migrationManager()->status();

        self::assertSame('', trim($output['stderr']));
        self::assertCount(1, $items);
        self::assertSame('202609240003', $items[0]['version']);
        self::assertSame('rolled_back', $items[0]['status']);
        self::assertSame(5, $this->countRows('migrations'));
        self::assertSame(5, $status['executed']);
        self::assertSame(1, $status['pending']);
        self::assertTrue($this->db()->createSchemaManager()->tablesExist([
            $this->tableNameResolver()->resolve('queue_message_attempts'),
        ]));
        self::assertTrue($this->db()->createSchemaManager()->tablesExist([
            $this->tableNameResolver()->resolve('connections'),
            $this->tableNameResolver()->resolve('connection_rate_limits'),
            $this->tableNameResolver()->resolve('weighted_round_robin_states'),
        ]));
    }

    /**
     * @since 0.1.0
     */
    public function testRollbackResetDropsAllManagedTables(): void
    {
        $output = $this->captureCli(function (): void {
            $this->migrationCommand()->rollback([], [
                'reset' => true,
                'yes' => true,
                'format' => 'json',
            ]);
        });
        $items = json_decode(trim($output['stdout']), true, flags: JSON_THROW_ON_ERROR);
        $status = $this->migrationManager()->status();

        self::assertSame('', trim($output['stderr']));
        self::assertCount(6, $items);
        self::assertSame('202609240003', $items[0]['version']);
        self::assertSame('202609240002', $items[1]['version']);
        self::assertSame('202609240001', $items[2]['version']);
        self::assertSame('202603300001', $items[3]['version']);
        self::assertSame('202603220001', $items[4]['version']);
        self::assertSame('202603190001', $items[5]['version']);
        self::assertSame(0, $this->countRows('migrations'));
        self::assertSame(0, $status['executed']);
        self::assertSame(6, $status['pending']);
        self::assertFalse($this->db()->createSchemaManager()->tablesExist([
            $this->tableNameResolver()->resolve('connections'),
            $this->tableNameResolver()->resolve('queue_messages'),
            $this->tableNameResolver()->resolve('queue_message_attempts'),
            $this->tableNameResolver()->resolve('mail_logs'),
            $this->tableNameResolver()->resolve('mail_attempts'),
            $this->tableNameResolver()->resolve('webhook_events'),
            $this->tableNameResolver()->resolve('connection_circuit_breakers'),
            $this->tableNameResolver()->resolve('connection_rate_limits'),
            $this->tableNameResolver()->resolve('weighted_round_robin_states'),
        ]));
    }
}

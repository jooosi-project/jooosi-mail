<?php

declare(strict_types=1);

namespace JooosiMail\Cli\Application;

use InvalidArgumentException;
use JooosiMail\Database\Migration\MigrationManager;
use JooosiMail\Database\Migration\MigrationStubGenerator;
use JooosiMail\Discovery\Attribute\Service;
use RuntimeException;

/**
 * Coordinates migration operations used by the WP-CLI adapter.
 *
 * The migration manager and stub generator remain the owners of migration
 * discovery, persistence, and execution. This service gives the command a
 * small application boundary without changing those database-layer contracts.
 *
 * @since 1.0.9
 */
#[Service]
final class MigrationCommandApplicationService
{
    public function __construct(
        private readonly MigrationManager $migrationManager,
        private readonly MigrationStubGenerator $migrationStubGenerator,
    ) {
    }

    /**
     * @param string $name
     *
     * @return array{path: string, className: class-string, version: string, description: string}
     *
     * @throws InvalidArgumentException|RuntimeException When the stub cannot be generated.
     *
     * @since 1.0.9
     */
    public function make(string $name): array
    {
        return $this->migrationStubGenerator->generate($name);
    }

    /**
     * @param list<string> $versions
     * @param bool $dryRun
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When execution fails before a result is returned.
     *
     * @since 1.0.9
     */
    public function run(array $versions, bool $dryRun): array
    {
        return $this->migrationManager->run($versions, $dryRun);
    }

    /**
     * @param int $steps
     * @param string|null $toVersion
     * @param bool $dryRun
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When rollback fails before a result is returned.
     *
     * @since 1.0.9
     */
    public function rollback(int $steps, ?string $toVersion, bool $dryRun): array
    {
        return $this->migrationManager->rollback($steps, $toVersion, $dryRun);
    }

    /**
     * @param bool $dryRun
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When reset fails before a result is returned.
     *
     * @since 1.0.9
     */
    public function reset(bool $dryRun): array
    {
        return $this->migrationManager->reset($dryRun);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Cli\Application;

use JooosiMail\Database\Migration\MigrationDefinition;
use JooosiMail\Database\Migration\MigrationManager;
use JooosiMail\Discovery\Attribute\Service;
use RuntimeException;
/**
 * Reads migration status and history for CLI use cases.
 *
 * @since 1.0.9
 */
#[Service]
final class MigrationQueryService
{
    public function __construct(private readonly MigrationManager $migrationManager)
    {
    }
    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException When migration status cannot be read.
     *
     * @since 1.0.9
     */
    public function status(): array
    {
        return $this->migrationManager->status();
    }
    /**
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException When migration history cannot be read.
     *
     * @since 1.0.9
     */
    public function list(): array
    {
        return $this->migrationManager->list();
    }
    /**
     * @return list<MigrationDefinition>
     *
     * @throws RuntimeException When pending migrations cannot be read.
     *
     * @since 1.0.9
     */
    public function pending(): array
    {
        return $this->migrationManager->pending();
    }
    /**
     * @throws RuntimeException When migration history cannot be read.
     *
     * @since 1.0.9
     */
    public function hasExecutedMigrations(): bool
    {
        return $this->migrationManager->hasExecutedMigrations();
    }
}

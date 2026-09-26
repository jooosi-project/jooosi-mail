<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration;

/**
 * Execution history row for a migration.
 *
 * @since 0.1.0
 */
final class MigrationExecution
{
    /**
     * @since 0.1.0
     */
    public function __construct(public readonly string $version, public readonly string $className, public readonly string $description, public readonly string $executedAt, public readonly int $executionTimeMs)
    {
    }
}

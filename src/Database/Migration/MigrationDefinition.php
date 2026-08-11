<?php

declare(strict_types=1);

namespace JooosiMail\Database\Migration;

/**
 * Immutable metadata for a discovered migration.
 *
 * @since 0.1.0
 */
final class MigrationDefinition
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $version,
        public readonly string $className,
        public readonly string $description,
    ) {
    }
}

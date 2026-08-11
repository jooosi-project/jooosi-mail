<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration;

use JooosiMail\Discovery\Attribute\Service;
use RuntimeException;
/**
 * Runs Jooosi Mail database migrations during activation.
 *
 * @since 0.1.0
 */
#[Service]
final class MigrationRunner
{
    public function __construct(private readonly \JooosiMail\Database\Migration\MigrationManager $migrationManager)
    {
    }
    /**
     * @since 0.1.0
     */
    public function run(): void
    {
        $result = $this->migrationManager->run();
        if (isset($result['failed'])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(esc_html($result['message']));
        }
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Cli;

use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Container\ContainerCache;
use WP_CLI;

/**
 * Inspect the Jooosi Mail compiled container cache.
 *
 * ## EXAMPLES
 *
 *     # Show whether the cached container is reusable.
 *     $ wp jooosi-mail container:status
 *     Usable: yes
 *     Reasons: none
 *
 *     # Clear the compiled container cache.
 *     $ wp jooosi-mail container:clear
 *     Success: Cleared the Jooosi Mail container cache. It will rebuild on the next boot.
 *
 * @since 0.1.0
 */
#[Service]
final class ContainerCommand
{
    public function __construct(
        private readonly ContainerCache $containerCache,
    ) {
    }

    /**
     * Show Jooosi Mail container cache status.
     *
     * ## EXAMPLES
     *
     *     # Show the current cache signature state.
     *     $ wp jooosi-mail container:status
     *     Usable: yes
     *     Reasons: none
     *     Environment: production
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Show Jooosi Mail container cache status.')]
    public function status(array $args, array $assocArgs): void
    {
        $status = $this->containerCache->inspect();

        WP_CLI::line(sprintf('Usable: %s', $status['usable'] ? 'yes' : 'no'));
        WP_CLI::line(sprintf('Reasons: %s', $status['reasons'] !== [] ? implode(', ', $status['reasons']) : 'none'));
        WP_CLI::line(sprintf('Environment: %s', $status['environment']));
        WP_CLI::line(sprintf('Debug: %s', $status['debug'] ? 'yes' : 'no'));
        WP_CLI::line(sprintf('Tracked Files: %d', $status['tracked_file_count']));
        WP_CLI::line(sprintf('Cache File: %s (%s)', $status['cache_file'], $status['cache_file_exists'] ? 'present' : 'missing'));
        WP_CLI::line(sprintf('Metadata File: %s (%s)', $status['metadata_file'], $status['metadata_file_exists'] ? 'present' : 'missing'));
        WP_CLI::line(sprintf('Generated At: %s', $status['generated_at'] ?? 'n/a'));
        WP_CLI::line(sprintf('Expected Class: %s', $status['expected_container_class']));
        WP_CLI::line(sprintf('Cached Class: %s', $status['cached_container_class'] ?? 'n/a'));
        WP_CLI::line(sprintf('Current Source Hash: %s', $this->shortenHash($status['current_source_hash'])));
        WP_CLI::line(sprintf('Cached Source Hash: %s', $this->shortenHash($status['cached_source_hash'])));
    }

    /**
     * Clear the Jooosi Mail container cache.
     *
     * ## EXAMPLES
     *
     *     # Force a rebuild on the next boot.
     *     $ wp jooosi-mail container:clear
     *     Success: Cleared the Jooosi Mail container cache. It will rebuild on the next boot.
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Clear the Jooosi Mail container cache.')]
    public function clear(array $args, array $assocArgs): void
    {
        $this->containerCache->clear();

        WP_CLI::success('Cleared the Jooosi Mail container cache. It will rebuild on the next boot.');
    }

    /**
     * @since 0.1.0
     */
    private function shortenHash(?string $hash): string
    {
        if (! is_string($hash) || $hash === '') {
            return 'n/a';
        }

        return substr($hash, 0, 12);
    }
}

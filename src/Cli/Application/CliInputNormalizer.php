<?php

declare(strict_types=1);

namespace JooosiMail\Cli\Application;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Normalizes common WP-CLI command arguments and options.
 *
 * @since 1.0.9
 */
#[Service]
final class CliInputNormalizer
{
    public function __construct(
        private readonly CliBooleanParser $booleanParser,
    ) {
    }

    /**
     * Resolve the first positional argument as a positive command id candidate.
     *
     * Validation and its WP-CLI error belong to the command adapter; this
     * method only preserves the command's existing scalar-to-integer rules.
     *
     * @param array<int, string> $args
     *
     * @since 1.0.9
     */
    public function firstIntegerArgument(array $args): int
    {
        return isset($args[0]) ? (int) $args[0] : 0;
    }

    /**
     * Resolve a boolean option using the shared robust parser.
     *
     * @param array<string, mixed> $assocArgs
     *
     * @since 1.0.9
     */
    public function booleanOption(array $assocArgs, string $option, bool $default = false): bool
    {
        return $this->booleanParser->parse($assocArgs[$option] ?? $default, $default);
    }
}

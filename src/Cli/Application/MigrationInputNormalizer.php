<?php

declare (strict_types=1);
namespace JooosiMail\Cli\Application;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Normalizes migration command arguments and options.
 *
 * Validation that needs WP-CLI output remains in the command adapter; this
 * service only performs deterministic input normalization and capability checks.
 *
 * @since 1.0.9
 */
#[Service]
final class MigrationInputNormalizer
{
    /**
     * @var list<string>
     */
    private const FORMATS = ['table', 'json', 'yaml', 'csv'];
    /**
     * @var list<string>
     */
    private const STATUS_FILTERS = ['all', 'executed', 'pending', 'executed_unavailable'];
    public function __construct(private readonly \JooosiMail\Cli\Application\CliBooleanParser $booleanParser)
    {
    }
    /**
     * @param array<int, string> $args
     *
     * @since 1.0.9
     */
    public function name(array $args): string
    {
        return sanitize_text_field((string) ($args[0] ?? ''));
    }
    /**
     * @param array<string, mixed> $assocArgs
     *
     * @since 1.0.9
     */
    public function format(array $assocArgs): string
    {
        return sanitize_text_field((string) ($assocArgs['format'] ?? 'table'));
    }
    /**
     * @since 1.0.9
     */
    public function supportsFormat(string $format): bool
    {
        return in_array($format, self::FORMATS, \true);
    }
    /**
     * @return list<string>
     *
     * @since 1.0.9
     */
    public function supportedFormats(): array
    {
        return self::FORMATS;
    }
    /**
     * @param array<string, mixed> $assocArgs
     *
     * @since 1.0.9
     */
    public function statusFilter(array $assocArgs): string
    {
        return sanitize_text_field((string) ($assocArgs['status'] ?? 'all'));
    }
    /**
     * @since 1.0.9
     */
    public function supportsStatusFilter(string $status): bool
    {
        return in_array($status, self::STATUS_FILTERS, \true);
    }
    /**
     * @return list<string>
     *
     * @since 1.0.9
     */
    public function supportedStatusFilters(): array
    {
        return self::STATUS_FILTERS;
    }
    /**
     * @param array<int, string> $versions
     *
     * @return list<string>
     *
     * @since 1.0.9
     */
    public function versions(array $versions): array
    {
        return array_values(array_filter(array_map(static fn(string $version): string => sanitize_text_field($version), $versions), static fn(string $version): bool => $version !== ''));
    }
    /**
     * @param array<string, mixed> $assocArgs
     *
     * @since 1.0.9
     */
    public function steps(array $assocArgs): int
    {
        return max(1, (int) ($assocArgs['steps'] ?? 1));
    }
    /**
     * @param array<string, mixed> $assocArgs
     *
     * @since 1.0.9
     */
    public function toVersion(array $assocArgs): ?string
    {
        return isset($assocArgs['to']) ? sanitize_text_field((string) $assocArgs['to']) : null;
    }
    /**
     * @param array<string, mixed> $assocArgs
     *
     * @since 1.0.9
     */
    public function flag(array $assocArgs, string $flag): bool
    {
        if (!array_key_exists($flag, $assocArgs)) {
            return \false;
        }
        return $this->booleanParser->parse($assocArgs[$flag]);
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Cli\Presentation;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Formats shared scalar values for WP-CLI table rows.
 *
 * @since 1.0.9
 */
#[Service]
final class CliValuePresenter
{
    /**
     * @since 1.0.9
     */
    public function boolean(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    /**
     * @param list<string> $values
     *
     * @since 1.0.9
     */
    public function commaSeparated(array $values): string
    {
        return implode(',', $values);
    }

    /**
     * @param list<string> $reasons
     *
     * @since 1.0.9
     */
    public function reasons(array $reasons): string
    {
        return $reasons === [] ? '-' : implode(', ', $reasons);
    }

    /**
     * @param array<string, array<string, mixed>> $windows
     *
     * @since 1.0.9
     */
    public function rateLimits(array $windows): string
    {
        $parts = [];

        foreach ($windows as $period => $window) {
            $limit = (int) ($window['limit'] ?? 0);

            if ($limit <= 0) {
                continue;
            }

            $remaining = max(0, (int) ($window['remaining'] ?? 0));
            $parts[] = sprintf('%s:%d/%d', $period, $remaining, $limit);
        }

        return $parts === [] ? '-' : implode(' ', $parts);
    }

    /**
     * @since 1.0.9
     */
    public function timestamp(mixed $timestamp): string
    {
        if (! is_numeric($timestamp)) {
            return '-';
        }

        return gmdate('Y-m-d H:i:s', (int) $timestamp);
    }
}

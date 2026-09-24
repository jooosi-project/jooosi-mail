<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Presentation;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Normalizes connection operational status for admin responses.
 *
 * @since 1.0.9
 */
#[Service]
final class OperationalStatusPresenter
{
    /**
     * @param array<string, mixed> $rateLimit
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function rateLimit(array $rateLimit): array
    {
        $windows = [];

        foreach ($rateLimit['windows'] ?? [] as $period => $window) {
            if (! is_array($window)) {
                continue;
            }

            $windows[(string) $period] = [
                'limit' => (int) ($window['limit'] ?? 0),
                'count' => (int) ($window['count'] ?? 0),
                'remaining' => isset($window['remaining']) ? (int) $window['remaining'] : null,
                'windowStartedAt' => $this->dateTime($window['window_started_at'] ?? null),
                'windowEndsAt' => $this->dateTime($window['window_ends_at'] ?? null),
                'exhausted' => (bool) ($window['exhausted'] ?? false),
            ];
        }

        return [
            'blocked' => (bool) ($rateLimit['blocked'] ?? false),
            'windows' => $windows,
        ];
    }

    /**
     * @param array<string, mixed> $circuitBreaker
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function circuitBreaker(array $circuitBreaker): array
    {
        return [
            'enabled' => (bool) ($circuitBreaker['enabled'] ?? false),
            'threshold' => (int) ($circuitBreaker['threshold'] ?? 0),
            'windowSeconds' => (int) ($circuitBreaker['window_seconds'] ?? 0),
            'cooldownSeconds' => (int) ($circuitBreaker['cooldown_seconds'] ?? 0),
            'recentFailures' => (int) ($circuitBreaker['recent_failures'] ?? 0),
            'blacklistedUntil' => $this->dateTime($circuitBreaker['blacklisted_until'] ?? null),
        ];
    }

    /**
     * @since 1.0.9
     */
    public function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return gmdate(DATE_ATOM, (int) $value);
        }

        return is_string($value) ? $value : null;
    }
}

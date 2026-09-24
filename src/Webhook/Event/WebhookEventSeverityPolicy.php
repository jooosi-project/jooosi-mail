<?php

declare(strict_types=1);

namespace JooosiMail\Webhook\Event;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Defines how normalized webhook feedback affects connection health.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookEventSeverityPolicy
{
    /**
     * @var array<string, array{penalty: int, affectsCircuitBreaker: bool}>
     */
    private const RULES = [
        'provider_unavailable' => ['penalty' => 20, 'affectsCircuitBreaker' => true],
        'outage' => ['penalty' => 20, 'affectsCircuitBreaker' => true],
        'temporarily_unavailable' => ['penalty' => 20, 'affectsCircuitBreaker' => true],
        'connection_error' => ['penalty' => 20, 'affectsCircuitBreaker' => true],
        'api_error' => ['penalty' => 20, 'affectsCircuitBreaker' => true],
        'complained' => ['penalty' => 15, 'affectsCircuitBreaker' => false],
        'complaint' => ['penalty' => 15, 'affectsCircuitBreaker' => false],
        'spam' => ['penalty' => 15, 'affectsCircuitBreaker' => false],
        'spam_report' => ['penalty' => 15, 'affectsCircuitBreaker' => false],
        'spam_complaint' => ['penalty' => 15, 'affectsCircuitBreaker' => false],
        'abuse' => ['penalty' => 15, 'affectsCircuitBreaker' => false],
        'bounce' => ['penalty' => 10, 'affectsCircuitBreaker' => false],
        'bounced' => ['penalty' => 10, 'affectsCircuitBreaker' => false],
        'hard_bounce' => ['penalty' => 10, 'affectsCircuitBreaker' => false],
        'rejected' => ['penalty' => 10, 'affectsCircuitBreaker' => true],
        'blocked' => ['penalty' => 10, 'affectsCircuitBreaker' => true],
        'dropped' => ['penalty' => 10, 'affectsCircuitBreaker' => true],
        'failed' => ['penalty' => 10, 'affectsCircuitBreaker' => true],
        'soft_bounce' => ['penalty' => 6, 'affectsCircuitBreaker' => false],
        'deferred' => ['penalty' => 6, 'affectsCircuitBreaker' => false],
        'delayed' => ['penalty' => 6, 'affectsCircuitBreaker' => false],
        'throttled' => ['penalty' => 6, 'affectsCircuitBreaker' => true],
        'rate_limited' => ['penalty' => 6, 'affectsCircuitBreaker' => true],
    ];

    /**
     * @since 1.0.9
     */
    public function penalty(string $eventType): int
    {
        return self::RULES[$this->normalize($eventType)]['penalty'] ?? 0;
    }

    /**
     * @since 1.0.9
     */
    public function affectsCircuitBreaker(string $eventType): bool
    {
        return self::RULES[$this->normalize($eventType)]['affectsCircuitBreaker'] ?? false;
    }

    /**
     * @since 1.0.9
     */
    private function normalize(string $eventType): string
    {
        return strtolower(trim($eventType));
    }
}

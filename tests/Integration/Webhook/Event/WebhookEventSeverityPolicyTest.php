<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Webhook\Event;

use JooosiMail\Webhook\Event\WebhookEventSeverityPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Covers the shared webhook health-severity policy.
 *
 * @since 1.0.9
 */
final class WebhookEventSeverityPolicyTest extends TestCase
{
    /**
     * @dataProvider severityProvider
     *
     * @since 1.0.9
     */
    public function testSeverityRulesPreservePenaltyAndCircuitBreakerBehavior(
        string $eventType,
        int $penalty,
        bool $affectsCircuitBreaker,
    ): void {
        $policy = new WebhookEventSeverityPolicy();

        self::assertSame($penalty, $policy->penalty($eventType));
        self::assertSame($affectsCircuitBreaker, $policy->affectsCircuitBreaker($eventType));
    }

    /**
     * @return array<string, array{string, int, bool}>
     *
     * @since 1.0.9
     */
    public static function severityProvider(): array
    {
        return [
            'provider outage' => ['provider_unavailable', 20, true],
            'complaint' => ['spam_complaint', 15, false],
            'hard bounce' => ['hard_bounce', 10, false],
            'rejection' => ['rejected', 10, true],
            'throttling' => [' THROTTLED ', 6, true],
            'deferred' => ['deferred', 6, false],
            'positive or unknown event' => ['delivered', 0, false],
        ];
    }
}

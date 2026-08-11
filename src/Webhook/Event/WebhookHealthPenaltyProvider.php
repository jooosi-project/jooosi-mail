<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Event;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Routing\ConnectionHealthPenaltyProviderInterface;
/**
 * Converts webhook delivery feedback into routing health penalties.
 *
 * @since 0.1.0
 */
#[Service]
final class WebhookHealthPenaltyProvider implements ConnectionHealthPenaltyProviderInterface
{
    /**
     * @var int
     */
    private const HOURS = 24;
    /**
     * @var int
     */
    private const SAMPLE_SIZE = 20;
    /**
     * @var int
     */
    private const MAX_PENALTY = 45;
    public function __construct(private readonly \JooosiMail\Webhook\Event\WebhookEventRepository $webhookEventRepository)
    {
    }
    /**
     * @param list<int> $connectionIds
     * @return array<int, int>
     *
     * @since 0.1.0
     */
    public function getNegativeHealthPenalties(array $connectionIds): array
    {
        $eventsByConnection = $this->webhookEventRepository->listRecentEventTypesByConnection($connectionIds, self::HOURS, self::SAMPLE_SIZE);
        $penalties = [];
        foreach ($connectionIds as $connectionId) {
            $penalty = 0;
            foreach ($eventsByConnection[$connectionId] ?? [] as $eventType) {
                $penalty += $this->getPenaltyForEventType($eventType);
            }
            $penalties[$connectionId] = min(self::MAX_PENALTY, $penalty);
        }
        return $penalties;
    }
    /**
     * @since 0.1.0
     */
    private function getPenaltyForEventType(string $eventType): int
    {
        return match ($eventType) {
            'provider_unavailable', 'outage', 'temporarily_unavailable', 'connection_error', 'api_error' => 20,
            'complained', 'complaint', 'spam', 'spam_report', 'spam_complaint', 'abuse' => 15,
            'bounce', 'bounced', 'hard_bounce', 'rejected', 'blocked', 'dropped', 'failed' => 10,
            'soft_bounce', 'deferred', 'delayed', 'throttled', 'rate_limited' => 6,
            default => 0,
        };
    }
}

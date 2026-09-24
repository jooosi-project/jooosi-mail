<?php

declare(strict_types=1);

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

    public function __construct(
        private readonly WebhookEventRepository $webhookEventRepository,
        private readonly WebhookEventSeverityPolicy $severityPolicy,
    ) {
    }

    /**
     * @param list<int> $connectionIds
     * @return array<int, int>
     *
     * @since 0.1.0
     */
    public function getNegativeHealthPenalties(array $connectionIds): array
    {
        $eventsByConnection = $this->webhookEventRepository->listRecentEventTypesByConnection(
            $connectionIds,
            self::HOURS,
            self::SAMPLE_SIZE,
        );
        $penalties = [];

        foreach ($connectionIds as $connectionId) {
            $penalty = 0;

            foreach ($eventsByConnection[$connectionId] ?? [] as $eventType) {
                $penalty += $this->severityPolicy->penalty($eventType);
            }

            $penalties[$connectionId] = min(self::MAX_PENALTY, $penalty);
        }

        return $penalties;
    }
}

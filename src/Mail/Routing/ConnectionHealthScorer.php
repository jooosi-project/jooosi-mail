<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Routing;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Logging\MailAttemptRepository;

/**
 * Builds simple health scores from recent delivery attempts.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionHealthScorer
{
    public function __construct(
        private readonly MailAttemptRepository $mailAttemptRepository,
        private readonly ConnectionHealthPenaltyProviderInterface $healthPenaltyProvider,
    ) {
    }

    /**
     * @param list<Connection> $connections
     * @return array<int, int>
     *
     * @since 0.1.0
     */
    public function score(array $connections): array
    {
        $connectionIds = array_values(array_filter(
            array_map(static fn (Connection $connection): ?int => $connection->id, $connections),
            static fn (?int $id): bool => $id !== null,
        ));

        $scores = $this->mailAttemptRepository->getHealthScores($connectionIds);
        $penalties = $this->healthPenaltyProvider->getNegativeHealthPenalties($connectionIds);

        foreach ($scores as $connectionId => $score) {
            $scores[$connectionId] = max(0, $score - ($penalties[$connectionId] ?? 0));
        }

        return $scores;
    }
}

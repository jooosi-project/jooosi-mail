<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionRepository;
/**
 * Builds operational status views for configured connections.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionStatusReporter
{
    public function __construct(private readonly ConnectionRepository $connectionRepository, private readonly \JooosiMail\Mail\Routing\ConnectionAvailabilityDecider $connectionAvailabilityDecider, private readonly \JooosiMail\Mail\Routing\ConnectionHealthScorer $connectionHealthScorer)
    {
    }
    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    public function getStatuses(bool $includeDisabled = \false): array
    {
        $connections = $includeDisabled ? $this->connectionRepository->findAll() : $this->connectionRepository->findActive();
        $healthScores = $this->connectionHealthScorer->score($connections);
        $statuses = [];
        foreach ($connections as $connection) {
            $availability = $this->connectionAvailabilityDecider->getStatus($connection);
            $statuses[] = ['connection' => $connection, 'health_score' => $connection->id !== null ? $healthScores[$connection->id] ?? 60 : 0, 'availability' => $availability];
        }
        return $statuses;
    }
    /**
     * @return array<string, int|null>
     *
     * @since 0.1.0
     */
    public function summarizeActiveConnections(): array
    {
        $statuses = $this->getStatuses();
        $summary = ['active_connections' => count($statuses), 'available_connections' => 0, 'temporarily_unavailable_connections' => 0, 'next_available_at' => null, 'next_available_in_seconds' => null];
        foreach ($statuses as $status) {
            $availability = $status['availability'];
            if ($availability['available'] ?? \false) {
                $summary['available_connections']++;
                continue;
            }
            $summary['temporarily_unavailable_connections']++;
            $nextAvailableAt = isset($availability['next_available_at']) ? (int) $availability['next_available_at'] : null;
            if ($nextAvailableAt === null) {
                continue;
            }
            if ($summary['next_available_at'] === null || $nextAvailableAt < $summary['next_available_at']) {
                $summary['next_available_at'] = $nextAvailableAt;
            }
        }
        if ($summary['next_available_at'] !== null) {
            $summary['next_available_in_seconds'] = max(1, $summary['next_available_at'] - time());
        }
        return $summary;
    }
}

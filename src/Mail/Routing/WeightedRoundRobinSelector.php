<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Routing\State\WeightedRoundRobinStateRepository;
/**
 * Selects the next primary connection using smooth weighted round robin.
 *
 * @since 0.1.0
 */
#[Service]
final class WeightedRoundRobinSelector
{
    /**
     * @var int
     */
    private const PRIMARY_HEALTH_THRESHOLD = 25;
    public function __construct(private readonly WeightedRoundRobinStateRepository $weightedRoundRobinStateRepository)
    {
    }
    /**
     * @param list<Connection> $connections
     * @param array<int, int> $healthScores
     *
     * @since 0.1.0
     */
    public function select(array $connections, array $healthScores): ?Connection
    {
        $eligibleConnections = array_values(array_filter($connections, fn(Connection $connection): bool => $this->isEligibleForPrimary($connection, $healthScores)));
        if ($eligibleConnections === []) {
            $eligibleConnections = $connections;
        }
        if ($eligibleConnections === []) {
            return null;
        }
        return $this->weightedRoundRobinStateRepository->update(function (array $state) use ($eligibleConnections, $healthScores): array {
            $state = $this->sanitizeState($state, $eligibleConnections);
            $selectedConnection = null;
            $highestWeight = null;
            $totalWeight = 0;
            foreach ($eligibleConnections as $connection) {
                if ($connection->id === null) {
                    continue;
                }
                $weight = max(1, $connection->weight);
                $state[$connection->id] = ($state[$connection->id] ?? 0) + $weight;
                $totalWeight += $weight;
                if ($selectedConnection === null || $this->isBetterSelection($connection, $selectedConnection, $state, $healthScores)) {
                    $selectedConnection = $connection;
                    $highestWeight = $state[$connection->id];
                }
            }
            if ($selectedConnection === null || $selectedConnection->id === null || $highestWeight === null) {
                return ['state' => $state, 'result' => null];
            }
            $state[$selectedConnection->id] = $highestWeight - $totalWeight;
            return ['state' => $state, 'result' => $selectedConnection];
        });
    }
    /**
     * @param array<int, int> $state
     * @param list<Connection> $connections
     * @return array<int, int>
     *
     * @since 0.1.0
     */
    private function sanitizeState(array $state, array $connections): array
    {
        $allowedIds = [];
        foreach ($connections as $connection) {
            if ($connection->id === null) {
                continue;
            }
            $allowedIds[$connection->id] = \true;
        }
        $sanitized = [];
        foreach ($state as $connectionId => $currentWeight) {
            $connectionId = (int) $connectionId;
            if (!isset($allowedIds[$connectionId])) {
                continue;
            }
            $sanitized[$connectionId] = (int) $currentWeight;
        }
        return $sanitized;
    }
    /**
     * @param array<int, int> $healthScores
     *
     * @since 0.1.0
     */
    private function isEligibleForPrimary(Connection $connection, array $healthScores): bool
    {
        if ($connection->id === null) {
            return \false;
        }
        return ($healthScores[$connection->id] ?? 60) >= self::PRIMARY_HEALTH_THRESHOLD;
    }
    /**
     * @param array<int, int> $state
     * @param array<int, int> $healthScores
     *
     * @since 0.1.0
     */
    private function isBetterSelection(Connection $candidate, Connection $selected, array $state, array $healthScores): bool
    {
        if ($candidate->id === null || $selected->id === null) {
            return \false;
        }
        $candidateWeight = $state[$candidate->id] ?? 0;
        $selectedWeight = $state[$selected->id] ?? 0;
        if ($candidateWeight !== $selectedWeight) {
            return $candidateWeight > $selectedWeight;
        }
        $candidateHealth = $healthScores[$candidate->id] ?? 60;
        $selectedHealth = $healthScores[$selected->id] ?? 60;
        if ($candidateHealth !== $selectedHealth) {
            return $candidateHealth > $selectedHealth;
        }
        if ($candidate->priority !== $selected->priority) {
            return $candidate->priority < $selected->priority;
        }
        return $candidate->id < $selected->id;
    }
}

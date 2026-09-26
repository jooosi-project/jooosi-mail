<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
/**
 * Selects the next primary connection using weighted random sampling.
 *
 * @since 0.1.0
 */
#[Service]
final class WeightedRandomSelector
{
    /**
     * @var int
     */
    private const PRIMARY_HEALTH_THRESHOLD = 25;
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
        $totalWeight = 0;
        foreach ($eligibleConnections as $connection) {
            $totalWeight += max(1, $connection->weight);
        }
        $selectedWeight = random_int(1, $totalWeight);
        $runningWeight = 0;
        foreach ($eligibleConnections as $connection) {
            $runningWeight += max(1, $connection->weight);
            if ($selectedWeight <= $runningWeight) {
                return $connection;
            }
        }
        return $eligibleConnections[array_key_last($eligibleConnections)];
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
}

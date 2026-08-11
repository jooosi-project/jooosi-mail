<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Routing;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionRepository;

/**
 * Resolves candidate connections for a delivery plan.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionResolver
{
    public function __construct(
        private readonly ConnectionRepository $connectionRepository,
        private readonly ConnectionAvailabilityDecider $connectionAvailabilityDecider,
        private readonly ConnectionHealthScorer $connectionHealthScorer,
        private readonly WeightedRoundRobinSelector $weightedRoundRobinSelector,
        private readonly WeightedRandomSelector $weightedRandomSelector,
    ) {
    }

    /**
     * @return list<Connection>
     *
     * @since 0.1.0
     */
    public function resolve(DeliveryPlan $deliveryPlan): array
    {
        $connections = $this->connectionAvailabilityDecider->filterAvailable($this->connectionRepository->findActive());

        if ($connections === []) {
            return [];
        }

        $healthScores = $this->connectionHealthScorer->score($connections);

        return match ($deliveryPlan->strategy) {
            RoutingStrategy::Single => $this->resolveSingleConnection(
                connections: $connections,
                healthScores: $healthScores,
                preferredConnectionId: $deliveryPlan->preferredConnectionId,
            ),
            RoutingStrategy::WeightedRandom => $this->orderConnections(
                connections: $connections,
                healthScores: $healthScores,
                preferredConnectionId: $deliveryPlan->preferredConnectionId,
                forcedPrimaryConnectionId: $deliveryPlan->preferredConnectionId ?? $this->weightedRandomSelector->select($connections, $healthScores)?->id,
            ),
            RoutingStrategy::RoundRobin => $this->orderConnections(
                connections: $connections,
                healthScores: $healthScores,
                preferredConnectionId: $deliveryPlan->preferredConnectionId,
                forcedPrimaryConnectionId: $deliveryPlan->preferredConnectionId ?? $this->weightedRoundRobinSelector->select($connections, $healthScores)?->id,
            ),
            RoutingStrategy::Failover => $this->orderConnections(
                connections: $connections,
                healthScores: $healthScores,
                preferredConnectionId: $deliveryPlan->preferredConnectionId,
            ),
        };
    }

    /**
     * @param list<Connection> $connections
     * @param array<int, int> $healthScores
     * @return list<Connection>
     *
     * @since 0.1.0
     */
    private function resolveSingleConnection(array $connections, array $healthScores, ?int $preferredConnectionId): array
    {
        $orderedConnections = $this->orderConnections(
            connections: $connections,
            healthScores: $healthScores,
            preferredConnectionId: $preferredConnectionId,
        );

        $primaryConnection = $orderedConnections === [] ? null : $orderedConnections[array_key_first($orderedConnections)];

        return $primaryConnection instanceof Connection ? [$primaryConnection] : [];
    }

    /**
     * @param list<Connection> $connections
     * @param array<int, int> $healthScores
     * @return list<Connection>
     *
     * @since 0.1.0
     */
    private function orderConnections(
        array $connections,
        array $healthScores,
        ?int $preferredConnectionId,
        ?int $forcedPrimaryConnectionId = null,
    ): array {
        usort($connections, function (Connection $left, Connection $right) use ($healthScores, $preferredConnectionId, $forcedPrimaryConnectionId): int {
            $leftScore = $this->getConnectionScore($left, $healthScores, $preferredConnectionId, $forcedPrimaryConnectionId);
            $rightScore = $this->getConnectionScore($right, $healthScores, $preferredConnectionId, $forcedPrimaryConnectionId);

            if ($leftScore !== $rightScore) {
                return $rightScore <=> $leftScore;
            }

            if ($left->priority !== $right->priority) {
                return $left->priority <=> $right->priority;
            }

            if ($left->weight !== $right->weight) {
                return $right->weight <=> $left->weight;
            }

            return ($left->id ?? PHP_INT_MAX) <=> ($right->id ?? PHP_INT_MAX);
        });

        return $connections;
    }

    /**
     * @param array<int, int> $healthScores
     *
     * @since 0.1.0
     */
    private function getConnectionScore(
        Connection $connection,
        array $healthScores,
        ?int $preferredConnectionId,
        ?int $forcedPrimaryConnectionId,
    ): int {
        $connectionId = $connection->id;
        $healthScore = $connectionId !== null ? ($healthScores[$connectionId] ?? 60) : 0;
        $score = $healthScore * 100;

        if ($connection->default) {
            $score += 500;
        }

        if ($connectionId !== null && $connectionId === $preferredConnectionId) {
            $score += 5000;
        }

        if ($connectionId !== null && $connectionId === $forcedPrimaryConnectionId) {
            $score += 10000;
        }

        return $score;
    }
}

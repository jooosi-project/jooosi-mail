<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Connection\Connection;
/**
 * Filters connections that are temporarily unavailable for routing.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionAvailabilityDecider
{
    public function __construct(private readonly \JooosiMail\Mail\Routing\ConnectionCircuitBreaker $connectionCircuitBreaker, private readonly \JooosiMail\Mail\Routing\ConnectionRateLimiter $connectionRateLimiter, private readonly EventPublisherInterface $eventPublisher)
    {
    }
    /**
     * @param list<Connection> $connections
     * @return list<Connection>
     *
     * @since 0.1.0
     */
    public function filterAvailable(array $connections): array
    {
        return array_values(array_filter($connections, fn(Connection $connection): bool => $this->isAvailable($connection)));
    }
    /**
     * @since 0.1.0
     */
    public function isAvailable(Connection $connection): bool
    {
        $status = $this->getStatus($connection);
        return (bool) $this->eventPublisher->applyFilters('f!jooosi-mail/routing:connection.available', $status['available'], $connection, $status);
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function getStatus(Connection $connection): array
    {
        $blacklistedUntil = $this->connectionCircuitBreaker->getBlacklistedUntil($connection);
        $rateLimit = $this->connectionRateLimiter->getAvailability($connection);
        $unavailableReasons = [];
        if ($blacklistedUntil !== null) {
            $unavailableReasons[] = 'circuit_breaker';
        }
        foreach ($rateLimit['windows'] ?? [] as $period => $window) {
            if (($window['exhausted'] ?? \false) === \true) {
                $unavailableReasons[] = 'rate_limit:' . $period;
            }
        }
        return ['available' => $blacklistedUntil === null && !$rateLimit['blocked'], 'blacklisted_until' => $blacklistedUntil, 'next_available_at' => $this->resolveNextAvailableAt($blacklistedUntil, $rateLimit), 'unavailable_reasons' => $unavailableReasons, 'rate_limit' => $rateLimit, 'circuit_breaker' => $this->connectionCircuitBreaker->getStatus($connection)];
    }
    /**
     * @param array<string, mixed> $rateLimit
     *
     * @since 0.1.0
     */
    private function resolveNextAvailableAt(?int $blacklistedUntil, array $rateLimit): ?int
    {
        $candidates = [];
        if ($blacklistedUntil !== null) {
            $candidates[] = $blacklistedUntil;
        }
        foreach ($rateLimit['windows'] ?? [] as $window) {
            if (($window['exhausted'] ?? \false) !== \true) {
                continue;
            }
            $endsAt = isset($window['window_ends_at']) ? (int) $window['window_ends_at'] : null;
            if ($endsAt !== null && $endsAt > time()) {
                $candidates[] = $endsAt;
            }
        }
        return $candidates === [] ? null : min($candidates);
    }
}

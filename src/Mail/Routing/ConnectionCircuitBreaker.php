<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Routing;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Routing\State\CircuitBreakerStateRepository;
use Throwable;

/**
 * Tracks repeated failures and temporarily sidelines unhealthy connections.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionCircuitBreaker
{
    /**
     * @var int
     */
    private const DEFAULT_THRESHOLD = 5;

    /**
     * @var int
     */
    private const DEFAULT_WINDOW = 300;

    /**
     * @var int
     */
    private const DEFAULT_COOLDOWN = 300;

    public function __construct(
        private readonly CircuitBreakerStateRepository $circuitBreakerStateRepository,
        private readonly OptionStore $optionStore,
        private readonly EventPublisherInterface $eventPublisher,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function recordFailure(Connection $connection, Throwable $throwable): void
    {
        if ($connection->id === null || ! $this->isEnabled($connection)) {
            return;
        }

        $window = $this->getWindowSeconds($connection);
        $threshold = $this->getThreshold($connection);
        $cooldown = $this->getCooldownSeconds($connection);
        $now = time();
        $state = $this->circuitBreakerStateRepository->recordFailure($connection->id, $window, $throwable->getMessage());
        $recentFailures = (int) ($state['recent_failure_count'] ?? 0);

        if ($recentFailures < $threshold) {
            return;
        }

        $blacklistUntil = $now + $cooldown;
        $this->circuitBreakerStateRepository->setBlacklistedUntil($connection->id, $blacklistUntil, $throwable->getMessage());

        $this->eventPublisher->doAction('a!jooosi-mail/routing/circuit-breaker:opened', $connection, $blacklistUntil, $throwable, $recentFailures);
    }

    /**
     * @since 0.1.0
     */
    public function recordSuccess(Connection $connection): void
    {
        if ($connection->id === null) {
            return;
        }

        $wasBlacklisted = $this->isBlacklisted($connection);
        $this->circuitBreakerStateRepository->clear($connection->id);

        if ($wasBlacklisted) {
            $this->eventPublisher->doAction('a!jooosi-mail/routing/circuit-breaker:closed', $connection);
        }
    }

    /**
     * @since 0.1.0
     */
    public function isBlacklisted(Connection $connection): bool
    {
        return $this->getBlacklistedUntil($connection) !== null;
    }

    /**
     * @since 0.1.0
     */
    public function getBlacklistedUntil(Connection $connection): ?int
    {
        if ($connection->id === null || ! $this->isEnabled($connection)) {
            return null;
        }

        $state = $this->circuitBreakerStateRepository->get($connection->id, $this->getWindowSeconds($connection));

        return isset($state['blacklisted_until']) && is_int($state['blacklisted_until']) && $state['blacklisted_until'] > time()
            ? $state['blacklisted_until']
            : null;
    }

    /**
     * @return array<string, int|null>
     *
     * @since 0.1.0
     */
    public function getStatus(Connection $connection): array
    {
        $blacklistedUntil = $this->getBlacklistedUntil($connection);
        $state = $connection->id !== null
            ? $this->circuitBreakerStateRepository->get($connection->id, $this->getWindowSeconds($connection))
            : ['recent_failure_count' => 0];

        return [
            'enabled' => $this->isEnabled($connection) ? 1 : 0,
            'threshold' => $this->getThreshold($connection),
            'window_seconds' => $this->getWindowSeconds($connection),
            'cooldown_seconds' => $this->getCooldownSeconds($connection),
            'recent_failures' => (int) ($state['recent_failure_count'] ?? 0),
            'blacklisted_until' => $blacklistedUntil,
        ];
    }

    /**
     * @since 0.1.0
     */
    private function isEnabled(Connection $connection): bool
    {
        return $this->getThreshold($connection) > 0 && $this->getCooldownSeconds($connection) > 0;
    }

    /**
     * @since 0.1.0
     */
    private function getThreshold(Connection $connection): int
    {
        $threshold = $connection->settings['circuit_breaker']['threshold']
            ?? $connection->settings['circuit_breaker_threshold']
            ?? $this->optionStore->get('settings.routing.circuit_breaker.threshold', self::DEFAULT_THRESHOLD);

        return max(0, (int) $this->eventPublisher->applyFilters('f!jooosi-mail/routing:circuit-breaker.threshold', (int) $threshold, $connection));
    }

    /**
     * @since 0.1.0
     */
    private function getWindowSeconds(Connection $connection): int
    {
        $window = $connection->settings['circuit_breaker']['window']
            ?? $connection->settings['circuit_breaker_window']
            ?? $this->optionStore->get('settings.routing.circuit_breaker.window_seconds', self::DEFAULT_WINDOW);

        return max(1, (int) $this->eventPublisher->applyFilters('f!jooosi-mail/routing:circuit-breaker.window', (int) $window, $connection));
    }

    /**
     * @since 0.1.0
     */
    private function getCooldownSeconds(Connection $connection): int
    {
        $cooldown = $connection->settings['circuit_breaker']['cooldown']
            ?? $connection->settings['circuit_breaker_cooldown']
            ?? $this->optionStore->get('settings.routing.circuit_breaker.cooldown_seconds', self::DEFAULT_COOLDOWN);

        return max(0, (int) $this->eventPublisher->applyFilters('f!jooosi-mail/routing:circuit-breaker.cooldown', (int) $cooldown, $connection));
    }
}

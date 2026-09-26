<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Routing\State\RateLimitStateRepository;
/**
 * Applies per-connection rate limits using persisted rolling windows.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionRateLimiter
{
    /** @var array<string, int> */
    private const WINDOWS = ['minute' => 60, 'hour' => 3600, 'day' => 86400];
    public function __construct(private readonly RateLimitStateRepository $rateLimitStateRepository, private readonly OptionStore $optionStore, private readonly EventPublisherInterface $eventPublisher)
    {
    }
    /**
     * @since 0.1.0
     */
    public function canSend(Connection $connection): bool
    {
        $availability = $this->getAvailability($connection);
        return (bool) $this->eventPublisher->applyFilters('f!jooosi-mail/routing:connection.rate-limit.can-send', !$availability['blocked'], $connection, $availability);
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function getAvailability(Connection $connection): array
    {
        $limits = $this->resolveLimits($connection);
        $windows = [];
        $blocked = \false;
        foreach ($limits as $period => $limit) {
            $state = $this->getWindowState($connection, $period);
            $remaining = $limit > 0 ? max(0, $limit - $state['count']) : null;
            $exhausted = $limit > 0 && $state['count'] >= $limit;
            if ($exhausted) {
                $blocked = \true;
            }
            $windows[$period] = ['limit' => $limit, 'count' => $state['count'], 'remaining' => $remaining, 'window_started_at' => $state['started_at'], 'window_ends_at' => $state['ends_at'], 'exhausted' => $exhausted];
        }
        return ['blocked' => $blocked, 'windows' => $windows];
    }
    /**
     * @since 0.1.0
     */
    public function recordSend(Connection $connection): void
    {
        if ($connection->id === null) {
            return;
        }
        foreach ($this->resolveLimits($connection) as $period => $limit) {
            if ($limit <= 0) {
                continue;
            }
            $this->rateLimitStateRepository->increment($connection->id, $period, self::WINDOWS[$period]);
        }
        $this->eventPublisher->doAction('a!jooosi-mail/routing/rate-limit:recorded', $connection);
    }
    /**
     * Atomically reserves rate-limit capacity before a provider request is made.
     *
     * @since 0.1.0
     */
    public function reserve(Connection $connection): bool
    {
        if ($connection->id === null) {
            return \true;
        }
        if (!$this->canSend($connection)) {
            return \false;
        }
        $reserved = $this->rateLimitStateRepository->reserve($connection->id, $this->resolveLimits($connection), self::WINDOWS);
        if ($reserved) {
            $this->eventPublisher->doAction('a!jooosi-mail/routing/rate-limit:reserved', $connection);
        }
        return $reserved;
    }
    /**
     * @return array<string, int>
     *
     * @since 0.1.0
     */
    private function resolveLimits(Connection $connection): array
    {
        $limits = [];
        foreach (array_keys(self::WINDOWS) as $period) {
            $limit = $this->resolveLimit($connection, $period);
            $limits[$period] = $limit > 0 ? $limit : 0;
        }
        return $limits;
    }
    /**
     * @since 0.1.0
     */
    private function resolveLimit(Connection $connection, string $period): int
    {
        $settingValue = $connection->settings['rate_limits'][$period] ?? $connection->settings['rate_limit_per_' . $period] ?? $connection->settings['rateLimitPer' . ucfirst($period)] ?? null;
        if ($settingValue === null) {
            $settingValue = $this->optionStore->get('settings.routing.rate_limits.' . $period, 0);
        }
        $limit = (int) $settingValue;
        return (int) $this->eventPublisher->applyFilters('f!jooosi-mail/routing:connection.rate-limit.' . $period, $limit, $connection);
    }
    /**
     * @return array{count: int, started_at: int, ends_at: int}
     *
     * @since 0.1.0
     */
    private function getWindowState(Connection $connection, string $period): array
    {
        $windowLength = self::WINDOWS[$period];
        $now = time();
        $defaultState = ['count' => 0, 'started_at' => $now, 'ends_at' => $now + $windowLength];
        if ($connection->id === null) {
            return $defaultState;
        }
        return $this->rateLimitStateRepository->get($connection->id, $period, $windowLength);
    }
}

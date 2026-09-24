<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Presentation;

use JooosiMail\Admin\Connection\AdminConnectionPresenter;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;

/**
 * Builds the dashboard-specific connection status projection.
 *
 * @since 1.0.9
 */
#[Service]
final class DashboardConnectionPresenter
{
    public function __construct(
        private readonly AdminConnectionPresenter $connectionPresenter,
        private readonly OperationalStatusPresenter $operationalStatusPresenter,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $statuses
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function presentMany(array $statuses): array
    {
        $payloads = [];

        foreach ($statuses as $status) {
            /** @var Connection $connection */
            $connection = $status['connection'];
            $availability = is_array($status['availability'] ?? null) ? $status['availability'] : [];
            $payload = $this->connectionPresenter->baseList($connection);
            $payload['healthScore'] = (int) ($status['health_score'] ?? 0);
            $payload['available'] = (bool) ($availability['available'] ?? false);
            $payload['unavailableReasons'] = array_values(array_map('strval', is_array($availability['unavailable_reasons'] ?? null) ? $availability['unavailable_reasons'] : []));
            $payload['nextAvailableAt'] = $this->operationalStatusPresenter->dateTime($availability['next_available_at'] ?? null);
            $payload['rateLimit'] = $this->operationalStatusPresenter->rateLimit(is_array($availability['rate_limit'] ?? null) ? $availability['rate_limit'] : []);
            $payload['circuitBreaker'] = $this->operationalStatusPresenter->circuitBreaker(is_array($availability['circuit_breaker'] ?? null) ? $availability['circuit_breaker'] : []);
            $payload['webhookUrl'] = $connection->id !== null ? rest_url('jooosi-mail/v1/webhook/' . $connection->id) : null;
            $payloads[] = $payload;
        }

        return $payloads;
    }
}

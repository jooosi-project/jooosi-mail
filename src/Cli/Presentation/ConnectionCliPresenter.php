<?php

declare (strict_types=1);
namespace JooosiMail\Cli\Presentation;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
/**
 * Builds the stable table rows emitted by the connection command.
 *
 * @since 1.0.9
 */
#[Service]
final class ConnectionCliPresenter
{
    public function __construct(private readonly \JooosiMail\Cli\Presentation\CliValuePresenter $valuePresenter)
    {
    }
    /**
     * @return array<string, string>
     *
     * @since 1.0.9
     */
    public function connection(Connection $connection): array
    {
        return ['id' => (string) ($connection->id ?? '-'), 'name' => $connection->name, 'profile' => $connection->profileKey, 'enabled' => $this->valuePresenter->boolean($connection->enabled), 'default' => $this->valuePresenter->boolean($connection->default), 'priority' => (string) $connection->priority, 'weight' => (string) $connection->weight, 'webhooks' => $this->valuePresenter->boolean($connection->webhookEnabled)];
    }
    /**
     * @param array<string, mixed> $profile
     *
     * @return array<string, string>
     *
     * @since 1.0.9
     */
    public function profile(array $profile): array
    {
        return ['key' => (string) $profile['key'], 'label' => (string) $profile['label'], 'schemes' => $this->valuePresenter->commaSeparated((array) $profile['schemes']), 'webhooks' => $this->valuePresenter->boolean(!empty($profile['supports_webhooks'])), 'fields' => $this->valuePresenter->commaSeparated(array_keys((array) $profile['configuration_fields']))];
    }
    /**
     * @param array<string, mixed> $status
     *
     * @return array<string, string>
     *
     * @since 1.0.9
     */
    public function status(array $status): array
    {
        /** @var Connection $connection */
        $connection = $status['connection'];
        $availability = is_array($status['availability'] ?? null) ? $status['availability'] : [];
        $rateLimit = $availability['rate_limit']['windows'] ?? [];
        return ['id' => (string) ($connection->id ?? '-'), 'name' => $connection->name, 'profile' => $connection->profileKey, 'enabled' => $this->valuePresenter->boolean($connection->enabled), 'default' => $this->valuePresenter->boolean($connection->default), 'health' => (string) ($status['health_score'] ?? 0), 'available' => $this->valuePresenter->boolean((bool) ($availability['available'] ?? \false)), 'reasons' => $this->valuePresenter->reasons((array) ($availability['unavailable_reasons'] ?? [])), 'blacklisted_until' => $this->valuePresenter->timestamp($availability['blacklisted_until'] ?? null), 'next_available_at' => $this->valuePresenter->timestamp($availability['next_available_at'] ?? null), 'rate_limits' => $this->valuePresenter->rateLimits(is_array($rateLimit) ? $rateLimit : [])];
    }
    /**
     * @since 1.0.9
     */
    public function timestamp(mixed $timestamp): string
    {
        return $this->valuePresenter->timestamp($timestamp);
    }
}

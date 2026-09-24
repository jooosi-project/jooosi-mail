<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Settings;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Logging\MailLogRetentionPolicy;
use JooosiMail\Mail\Routing\DeliveryMode;
use JooosiMail\Mail\Routing\RoutingStrategy;
use JooosiMail\Mail\Sender\SenderPolicyResolver;

/**
 * Projects persisted settings into the stable admin REST representation.
 *
 * @since 1.0.9
 */
#[Service]
final class SettingsPayloadFactory
{
    /**
     * @since 1.0.9
     */
    public function __construct(
        private readonly OptionStore $optionStore,
        private readonly SettingsOptions $settingsOptions,
    ) {
    }

    /**
     * @return array{settings: array<string, mixed>, options: array<string, mixed>}
     *
     * @since 1.0.9
     */
    public function create(): array
    {
        return [
            'settings' => $this->settings(),
            'options' => [
                'deliveryModes' => $this->settingsOptions->deliveryModes(),
                'routingStrategies' => $this->settingsOptions->routingStrategies(),
                'returnPathModes' => $this->settingsOptions->returnPathModes(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function settings(): array
    {
        return [
            'mail' => [
                'intercept' => [
                    'enabled' => (bool) $this->optionStore->get('settings.mail.intercept.enabled', true),
                ],
                'sender' => [
                    'email' => (string) $this->optionStore->get('settings.mail.sender.email', ''),
                    'name' => (string) $this->optionStore->get('settings.mail.sender.name', ''),
                    'forceEmail' => (bool) $this->optionStore->get('settings.mail.sender.force_email', false),
                    'forceName' => (bool) $this->optionStore->get('settings.mail.sender.force_name', false),
                    'returnPathMode' => (string) $this->optionStore->get('settings.mail.sender.return_path_mode', SenderPolicyResolver::RETURN_PATH_MODE_PROVIDER_DEFAULT),
                    'returnPathEmail' => (string) $this->optionStore->get('settings.mail.sender.return_path_email', ''),
                ],
            ],
            'logging' => [
                'email' => [
                    'enabled' => (bool) $this->optionStore->get(MailLogRetentionPolicy::ENABLED_PATH, true),
                    'retentionDays' => $this->getConfiguredRetentionDays(),
                ],
            ],
            'delivery' => [
                'mode' => (string) $this->optionStore->get('settings.delivery.mode', DeliveryMode::Async->value),
                'strategy' => (string) $this->optionStore->get('settings.delivery.strategy', RoutingStrategy::WeightedRandom->value),
            ],
            'routing' => [
                'rateLimits' => [
                    'minute' => (int) $this->optionStore->get('settings.routing.rate_limits.minute', 0),
                    'hour' => (int) $this->optionStore->get('settings.routing.rate_limits.hour', 0),
                    'day' => (int) $this->optionStore->get('settings.routing.rate_limits.day', 0),
                ],
                'circuitBreaker' => [
                    'threshold' => (int) $this->optionStore->get('settings.routing.circuit_breaker.threshold', 5),
                    'windowSeconds' => (int) $this->optionStore->get('settings.routing.circuit_breaker.window_seconds', 300),
                    'cooldownSeconds' => (int) $this->optionStore->get('settings.routing.circuit_breaker.cooldown_seconds', 300),
                ],
            ],
            'queue' => [
                'retry' => [
                    'maxRetries' => (int) $this->optionStore->get('settings.queue.retry.max_retries', 3),
                    'delaySeconds' => (int) $this->optionStore->get('settings.queue.retry.delay_seconds', 60),
                    'multiplier' => (int) $this->optionStore->get('settings.queue.retry.multiplier', 2),
                    'maxDelaySeconds' => (int) $this->optionStore->get('settings.queue.retry.max_delay_seconds', 900),
                ],
            ],
        ];
    }

    /**
     * @since 1.0.9
     */
    private function getConfiguredRetentionDays(): ?int
    {
        $value = $this->optionStore->get(MailLogRetentionPolicy::RETENTION_DAYS_PATH);

        if (! is_numeric($value)) {
            return null;
        }

        $days = (int) $value;

        return $days > 0 ? $days : null;
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller;

use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Logging\MailLogRetentionPolicy;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Mail\Routing\DeliveryMode;
use JooosiMail\Mail\Routing\RoutingStrategy;
use JooosiMail\Mail\Sender\SenderPolicyResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes plugin-wide admin settings.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/settings')]
final class SettingsController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly OptionStore $optionStore,
        private readonly MailLogRetentionService $mailLogRetentionService,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->createPayload());
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: ['PUT', 'PATCH'], permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $body = $request->get_json_params();
        $settings = is_array($body['settings'] ?? null) ? $body['settings'] : (is_array($body) ? $body : []);

        if ($settings === []) {
            return new WP_Error('jooosi_mail_invalid_settings', 'A settings payload is required.', ['status' => 400]);
        }

        $delivery = is_array($settings['delivery'] ?? null) ? $settings['delivery'] : [];
        $mail = is_array($settings['mail'] ?? null) ? $settings['mail'] : [];
        $logging = is_array($settings['logging'] ?? null) ? $settings['logging'] : [];
        $routing = is_array($settings['routing'] ?? null) ? $settings['routing'] : [];
        $queue = is_array($settings['queue'] ?? null) ? $settings['queue'] : [];
        $mailIntercept = is_array($mail['intercept'] ?? null) ? $mail['intercept'] : [];
        $mailSender = is_array($mail['sender'] ?? null) ? $mail['sender'] : [];
        $emailLogging = is_array($logging['email'] ?? null) ? $logging['email'] : [];
        $routingRateLimits = is_array($routing['rateLimits'] ?? null) ? $routing['rateLimits'] : [];
        $routingCircuitBreaker = is_array($routing['circuitBreaker'] ?? null) ? $routing['circuitBreaker'] : [];
        $queueRetry = is_array($queue['retry'] ?? null) ? $queue['retry'] : [];
        $deliveryMode = (string) ($delivery['mode'] ?? DeliveryMode::Async->value);
        $routingStrategy = (string) ($delivery['strategy'] ?? RoutingStrategy::WeightedRandom->value);
        $senderSettings = $this->normalizeSenderSettings($mailSender);
        $retentionDays = $this->normalizeRetentionDays($emailLogging['retentionDays'] ?? null);

        if ($senderSettings instanceof WP_Error) {
            return $senderSettings;
        }

        if ($retentionDays instanceof WP_Error) {
            return $retentionDays;
        }

        if (! in_array($deliveryMode, array_column($this->getDeliveryModeOptions(), 'value'), true)) {
            return new WP_Error('jooosi_mail_invalid_delivery_mode', 'The selected delivery mode is not supported.', ['status' => 400]);
        }

        if (! in_array($routingStrategy, array_column($this->getRoutingStrategyOptions(), 'value'), true)) {
            return new WP_Error('jooosi_mail_invalid_routing_strategy', 'The selected routing strategy is not supported.', ['status' => 400]);
        }

        $this->optionStore->set('settings.mail.intercept.enabled', (bool) ($mailIntercept['enabled'] ?? true));
        $this->optionStore->set('settings.mail.sender.email', $senderSettings['email']);
        $this->optionStore->set('settings.mail.sender.name', $senderSettings['name']);
        $this->optionStore->set('settings.mail.sender.force_email', $senderSettings['forceEmail']);
        $this->optionStore->set('settings.mail.sender.force_name', $senderSettings['forceName']);
        $this->optionStore->set('settings.mail.sender.return_path_mode', $senderSettings['returnPathMode']);
        $this->optionStore->set('settings.mail.sender.return_path_email', $senderSettings['returnPathEmail']);
        $this->optionStore->set(MailLogRetentionPolicy::ENABLED_PATH, (bool) ($emailLogging['enabled'] ?? true));
        $this->optionStore->set(MailLogRetentionPolicy::RETENTION_DAYS_PATH, $retentionDays);
        $this->optionStore->set('settings.delivery.mode', $deliveryMode);
        $this->optionStore->set('settings.delivery.strategy', $routingStrategy);
        $this->optionStore->set('settings.routing.rate_limits.minute', max(0, (int) ($routingRateLimits['minute'] ?? 0)));
        $this->optionStore->set('settings.routing.rate_limits.hour', max(0, (int) ($routingRateLimits['hour'] ?? 0)));
        $this->optionStore->set('settings.routing.rate_limits.day', max(0, (int) ($routingRateLimits['day'] ?? 0)));
        $this->optionStore->set('settings.routing.circuit_breaker.threshold', max(0, (int) ($routingCircuitBreaker['threshold'] ?? 5)));
        $this->optionStore->set('settings.routing.circuit_breaker.window_seconds', max(1, (int) ($routingCircuitBreaker['windowSeconds'] ?? 300)));
        $this->optionStore->set('settings.routing.circuit_breaker.cooldown_seconds', max(0, (int) ($routingCircuitBreaker['cooldownSeconds'] ?? 300)));
        $this->optionStore->set('settings.queue.retry.max_retries', max(0, (int) ($queueRetry['maxRetries'] ?? 3)));
        $this->optionStore->set('settings.queue.retry.delay_seconds', max(1, (int) ($queueRetry['delaySeconds'] ?? 60)));
        $this->optionStore->set('settings.queue.retry.multiplier', max(1, (int) ($queueRetry['multiplier'] ?? 2)));
        $this->optionStore->set('settings.queue.retry.max_delay_seconds', max(1, (int) ($queueRetry['maxDelaySeconds'] ?? 900)));
        $this->mailLogRetentionService->pruneExpired();

        return new WP_REST_Response($this->createPayload());
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function createPayload(): array
    {
        return [
            'settings' => [
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
            ],
            'options' => [
                'deliveryModes' => $this->getDeliveryModeOptions(),
                'routingStrategies' => $this->getRoutingStrategyOptions(),
                'returnPathModes' => $this->getReturnPathModeOptions(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $sender
     * @return array{email: string, name: string, forceEmail: bool, forceName: bool, returnPathMode: string, returnPathEmail: string}|WP_Error
     *
     * @since 0.1.0
     */
    private function normalizeSenderSettings(array $sender): array|WP_Error
    {
        $email = $this->normalizeOptionalEmail($sender['email'] ?? '', 'jooosi_mail_invalid_sender_email', 'Enter a valid From Email address.');

        if ($email instanceof WP_Error) {
            return $email;
        }

        $returnPathMode = strtolower(trim((string) ($sender['returnPathMode'] ?? SenderPolicyResolver::RETURN_PATH_MODE_PROVIDER_DEFAULT)));

        if (! in_array($returnPathMode, array_column($this->getReturnPathModeOptions(), 'value'), true)) {
            return new WP_Error('jooosi_mail_invalid_return_path_mode', 'The selected return-path mode is not supported.', ['status' => 400]);
        }

        $returnPathEmail = $this->normalizeOptionalEmail($sender['returnPathEmail'] ?? '', 'jooosi_mail_invalid_return_path_email', 'Enter a valid Return-Path email address.');

        if ($returnPathEmail instanceof WP_Error) {
            return $returnPathEmail;
        }

        if ($returnPathMode === SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM && $returnPathEmail === '') {
            return new WP_Error('jooosi_mail_missing_return_path_email', 'A custom Return-Path email address is required.', ['status' => 400]);
        }

        return [
            'email' => $email,
            'name' => sanitize_text_field((string) ($sender['name'] ?? '')),
            'forceEmail' => (bool) ($sender['forceEmail'] ?? false),
            'forceName' => (bool) ($sender['forceName'] ?? false),
            'returnPathMode' => $returnPathMode,
            'returnPathEmail' => $returnPathEmail,
        ];
    }

    /**
     * @since 0.1.0
     */
    private function normalizeOptionalEmail(mixed $value, string $errorCode, string $message): string|WP_Error
    {
        $email = is_scalar($value) ? trim((string) $value) : '';

        if ($email === '') {
            return '';
        }

        $email = sanitize_email($email);

        if (! is_email($email)) {
            return new WP_Error($errorCode, $message, ['status' => 400]);
        }

        return $email;
    }

    /**
     * @since 0.1.0
     */
    private function normalizeRetentionDays(mixed $value): int|null|WP_Error
    {
        if ($value === null || $value === '' || $value === 'forever') {
            return null;
        }

        if (is_numeric($value)) {
            $days = (int) $value;

            return $days > 0 ? $days : null;
        }

        return new WP_Error('jooosi_mail_invalid_log_retention', 'Enter a valid email log retention duration.', ['status' => 400]);
    }

    /**
     * @since 0.1.0
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

    /**
     * @return list<array{value: string, label: string}>
     *
     * @since 0.1.0
     */
    private function getDeliveryModeOptions(): array
    {
        return [
            ['value' => DeliveryMode::Async->value, 'label' => 'Async queue'],
            ['value' => DeliveryMode::Sync->value, 'label' => 'Sync'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     *
     * @since 0.1.0
     */
    private function getRoutingStrategyOptions(): array
    {
        return [
            ['value' => RoutingStrategy::WeightedRandom->value, 'label' => 'Weighted random'],
            ['value' => RoutingStrategy::RoundRobin->value, 'label' => 'Round robin'],
            ['value' => RoutingStrategy::Failover->value, 'label' => 'Failover'],
            ['value' => RoutingStrategy::Single->value, 'label' => 'Single connection'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     *
     * @since 0.1.0
     */
    private function getReturnPathModeOptions(): array
    {
        return [
            ['value' => SenderPolicyResolver::RETURN_PATH_MODE_PROVIDER_DEFAULT, 'label' => 'Provider default'],
            ['value' => SenderPolicyResolver::RETURN_PATH_MODE_MATCH_FROM, 'label' => 'Match From Email'],
            ['value' => SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM, 'label' => 'Custom address'],
        ];
    }
}

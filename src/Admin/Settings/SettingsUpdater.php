<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Settings;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Logging\MailLogRetentionPolicy;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Mail\Routing\DeliveryMode;
use JooosiMail\Mail\Routing\RoutingStrategy;
use JooosiMail\Mail\Sender\SenderPolicyResolver;
/**
 * Validates and persists full or partial admin settings updates.
 *
 * @since 1.0.9
 */
#[Service]
final class SettingsUpdater
{
    /**
     * @since 1.0.9
     */
    public function __construct(private readonly OptionStore $optionStore, private readonly MailLogRetentionService $mailLogRetentionService, private readonly \JooosiMail\Admin\Settings\SettingsPayloadFactory $settingsPayloadFactory, private readonly \JooosiMail\Admin\Settings\SettingsOptions $settingsOptions)
    {
    }
    /**
     * @param array<string, mixed> $settings
     *
     * @since 1.0.9
     */
    public function update(array $settings, bool $partial): void
    {
        if ($partial) {
            $settings = $this->mergePartial($this->settingsPayloadFactory->settings(), $settings);
        }
        $delivery = $this->section($settings, 'delivery');
        $mail = $this->section($settings, 'mail');
        $logging = $this->section($settings, 'logging');
        $routing = $this->section($settings, 'routing');
        $queue = $this->section($settings, 'queue');
        $mailIntercept = $this->section($mail, 'intercept');
        $mailSender = $this->section($mail, 'sender');
        $emailLogging = $this->section($logging, 'email');
        $routingRateLimits = $this->section($routing, 'rateLimits');
        $routingCircuitBreaker = $this->section($routing, 'circuitBreaker');
        $queueRetry = $this->section($queue, 'retry');
        $deliveryMode = $this->stringValue($delivery['mode'] ?? DeliveryMode::Async->value);
        $routingStrategy = $this->stringValue($delivery['strategy'] ?? RoutingStrategy::WeightedRandom->value);
        $senderSettings = $this->normalizeSenderSettings($mailSender);
        $retentionDays = $this->normalizeRetentionDays($emailLogging['retentionDays'] ?? null);
        if (!$this->settingsOptions->contains($this->settingsOptions->deliveryModes(), $deliveryMode)) {
            throw new \JooosiMail\Admin\Settings\SettingsValidationException('jooosi_mail_invalid_delivery_mode', 'The selected delivery mode is not supported.');
        }
        if (!$this->settingsOptions->contains($this->settingsOptions->routingStrategies(), $routingStrategy)) {
            throw new \JooosiMail\Admin\Settings\SettingsValidationException('jooosi_mail_invalid_routing_strategy', 'The selected routing strategy is not supported.');
        }
        $this->optionStore->setMany(['settings.mail.intercept.enabled' => (bool) ($mailIntercept['enabled'] ?? \true), 'settings.mail.sender.email' => $senderSettings['email'], 'settings.mail.sender.name' => $senderSettings['name'], 'settings.mail.sender.force_email' => $senderSettings['forceEmail'], 'settings.mail.sender.force_name' => $senderSettings['forceName'], 'settings.mail.sender.return_path_mode' => $senderSettings['returnPathMode'], 'settings.mail.sender.return_path_email' => $senderSettings['returnPathEmail'], MailLogRetentionPolicy::ENABLED_PATH => (bool) ($emailLogging['enabled'] ?? \true), MailLogRetentionPolicy::RETENTION_DAYS_PATH => $retentionDays, 'settings.delivery.mode' => $deliveryMode, 'settings.delivery.strategy' => $routingStrategy, 'settings.routing.rate_limits.minute' => max(0, (int) ($routingRateLimits['minute'] ?? 0)), 'settings.routing.rate_limits.hour' => max(0, (int) ($routingRateLimits['hour'] ?? 0)), 'settings.routing.rate_limits.day' => max(0, (int) ($routingRateLimits['day'] ?? 0)), 'settings.routing.circuit_breaker.threshold' => max(0, (int) ($routingCircuitBreaker['threshold'] ?? 5)), 'settings.routing.circuit_breaker.window_seconds' => max(1, (int) ($routingCircuitBreaker['windowSeconds'] ?? 300)), 'settings.routing.circuit_breaker.cooldown_seconds' => max(0, (int) ($routingCircuitBreaker['cooldownSeconds'] ?? 300)), 'settings.queue.retry.max_retries' => max(0, (int) ($queueRetry['maxRetries'] ?? 3)), 'settings.queue.retry.delay_seconds' => max(1, (int) ($queueRetry['delaySeconds'] ?? 60)), 'settings.queue.retry.multiplier' => max(1, (int) ($queueRetry['multiplier'] ?? 2)), 'settings.queue.retry.max_delay_seconds' => max(1, (int) ($queueRetry['maxDelaySeconds'] ?? 900))]);
        $this->mailLogRetentionService->pruneExpired();
    }
    /**
     * @param array<string, mixed> $sender
     * @return array{email: string, name: string, forceEmail: bool, forceName: bool, returnPathMode: string, returnPathEmail: string}
     *
     * @since 1.0.9
     */
    private function normalizeSenderSettings(array $sender): array
    {
        $email = $this->normalizeOptionalEmail($sender['email'] ?? '', 'jooosi_mail_invalid_sender_email', 'Enter a valid From Email address.');
        $returnPathMode = strtolower(trim($this->stringValue($sender['returnPathMode'] ?? SenderPolicyResolver::RETURN_PATH_MODE_PROVIDER_DEFAULT)));
        if (!$this->settingsOptions->contains($this->settingsOptions->returnPathModes(), $returnPathMode)) {
            throw new \JooosiMail\Admin\Settings\SettingsValidationException('jooosi_mail_invalid_return_path_mode', 'The selected return-path mode is not supported.');
        }
        $returnPathEmail = $this->normalizeOptionalEmail($sender['returnPathEmail'] ?? '', 'jooosi_mail_invalid_return_path_email', 'Enter a valid Return-Path email address.');
        if ($returnPathMode === SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM && $returnPathEmail === '') {
            throw new \JooosiMail\Admin\Settings\SettingsValidationException('jooosi_mail_missing_return_path_email', 'A custom Return-Path email address is required.');
        }
        return ['email' => $email, 'name' => sanitize_text_field($this->stringValue($sender['name'] ?? '')), 'forceEmail' => (bool) ($sender['forceEmail'] ?? \false), 'forceName' => (bool) ($sender['forceName'] ?? \false), 'returnPathMode' => $returnPathMode, 'returnPathEmail' => $returnPathEmail];
    }
    /**
     * @since 1.0.9
     */
    private function normalizeOptionalEmail(mixed $value, string $errorCode, string $message): string
    {
        $email = trim($this->stringValue($value));
        if ($email === '') {
            return '';
        }
        $email = sanitize_email($email);
        if (!is_email($email)) {
            throw new \JooosiMail\Admin\Settings\SettingsValidationException($errorCode, $message);
        }
        return $email;
    }
    /**
     * @since 1.0.9
     */
    private function normalizeRetentionDays(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'forever') {
            return null;
        }
        if (is_numeric($value)) {
            $days = (int) $value;
            return $days > 0 ? $days : null;
        }
        throw new \JooosiMail\Admin\Settings\SettingsValidationException('jooosi_mail_invalid_log_retention', 'Enter a valid email log retention duration.');
    }
    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    private function section(array $settings, string $key): array
    {
        return is_array($settings[$key] ?? null) ? $settings[$key] : [];
    }
    /**
     * @since 1.0.9
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $updates
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    private function mergePartial(array $current, array $updates): array
    {
        foreach ($updates as $key => $value) {
            if (is_array($current[$key] ?? null)) {
                if (!is_array($value)) {
                    throw new \JooosiMail\Admin\Settings\SettingsValidationException('jooosi_mail_invalid_settings', 'Settings sections must use an object payload.');
                }
                $current[$key] = $this->mergePartial($current[$key], $value);
                continue;
            }
            $current[$key] = $value;
        }
        return $current;
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Alert;

use InvalidArgumentException;
use RuntimeException;
use JooosiMail\Alert\Channel\AlertChannelDriverInterface;
use JooosiMail\Alert\Channel\AlertChannelRegistry;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Security\SecretCipher;
use JooosiMail\Infrastructure\WordPress\OptionStore;

/**
 * Reads and writes alert channel settings while keeping credentials encrypted.
 *
 * @since 1.0.12
 */
#[Service]
final class AlertConfigurationService
{
    /**
     * @since 1.0.12
     */
    public function __construct(
        private readonly OptionStore $optionStore,
        private readonly SecretCipher $secretCipher,
        private readonly AlertChannelRegistry $channelRegistry,
    ) {
    }

    /**
     * Return channel state safe for the admin API.
     *
     * @return array<string, array<string, bool|string>>
     *
     * @since 1.0.12
     */
    public function publicSettings(): array
    {
        $settings = [];

        foreach ($this->channelRegistry->all() as $channel) {
            $configuration = $this->credentials($channel->key());
            $settings[$channel->key()] = [
                'enabled' => $configuration['enabled'],
                'configured' => $configuration['configured'],
                'hasSavedCredential' => $configuration['hasSavedCredential'],
                'target' => $configuration['target'],
            ];
        }

        return $settings;
    }

    /**
     * Save a channel configuration. A blank secret keeps the stored secret.
     *
     * @param array<string, mixed> $payload
     * @return array<string, array<string, bool|string>>
     *
     * @since 1.0.12
     */
    public function save(string $channelKey, array $payload): array
    {
        $channel = $this->channelRegistry->get($channelKey);
        $current = $this->credentials($channelKey);
        $secretField = $channel->secretOptionField();
        $targetField = $channel->targetOptionField();
        $secretInput = trim($this->stringValue($payload['secret'] ?? $payload[$secretField] ?? ''));
        $secret = $current['secret'];

        if ($secretInput !== '') {
            $channel->validateSecret($secretInput);
            $secret = $secretInput;
        }

        $targetInputKey = array_key_exists('target', $payload) ? 'target' : $targetField;
        $target = $targetField !== null && $targetInputKey !== null && array_key_exists($targetInputKey, $payload)
            ? trim(sanitize_text_field($this->stringValue($payload[$targetInputKey])))
            : $current['target'];
        $enabled = array_key_exists('enabled', $payload)
            ? $this->booleanValue($payload['enabled'])
            : $current['enabled'];

        if ($targetField !== null && $target !== '') {
            $channel->validateTarget($target);
        }

        if ($enabled && ! $this->isConfigured($channel, $secret, $target)) {
            throw new InvalidArgumentException(sprintf('Complete the %s settings before enabling alerts.', $channel->displayName()));
        }

        $values = [
            $this->path($channelKey, 'enabled') => $enabled,
        ];

        if ($targetField !== null) {
            $values[$this->path($channelKey, $targetField)] = $target;
        }

        if ($secretInput !== '') {
            $values[$this->path($channelKey, $secretField)] = $this->secretCipher->encrypt($secretInput);
        }

        $this->optionStore->setMany($values);

        return $this->publicSettings();
    }

    /**
     * Disable a channel and remove its stored secret and destination.
     *
     * @return array<string, array<string, bool|string>>
     *
     * @since 1.0.12
     */
    public function disconnect(string $channelKey): array
    {
        $channel = $this->channelRegistry->get($channelKey);
        $values = [
            $this->path($channelKey, 'enabled') => false,
            $this->path($channelKey, $channel->secretOptionField()) => '',
        ];
        $targetField = $channel->targetOptionField();

        if ($targetField !== null) {
            $values[$this->path($channelKey, $targetField)] = '';
        }

        $this->optionStore->setMany($values);

        return $this->publicSettings();
    }

    /**
     * Return decrypted credentials for an internal channel delivery.
     *
     * @return array{enabled: bool, configured: bool, hasSavedCredential: bool, secret: string, target: string}
     *
     * @since 1.0.12
     */
    public function credentials(string $channelKey): array
    {
        $channel = $this->channelRegistry->get($channelKey);
        $storedSecret = (string) $this->optionStore->get($this->path($channelKey, $channel->secretOptionField()), '');
        $secret = '';

        if ($storedSecret !== '') {
            try {
                $secret = $this->secretCipher->decrypt($storedSecret);
            } catch (RuntimeException) {
                $secret = '';
            }
        }
        $targetField = $channel->targetOptionField();
        $target = $targetField !== null
            ? (string) $this->optionStore->get($this->path($channelKey, $targetField), '')
            : '';

        return [
            'enabled' => (bool) $this->optionStore->get($this->path($channelKey, 'enabled'), false),
            'configured' => $this->isConfigured($channel, $secret, $target),
            'hasSavedCredential' => $storedSecret !== '',
            'secret' => $secret,
            'target' => $target,
        ];
    }

    /**
     * @since 1.0.12
     */
    private function isConfigured(
        AlertChannelDriverInterface $channel,
        string $secret,
        string $target,
    ): bool {
        return $secret !== '' && ($channel->targetOptionField() === null || $target !== '');
    }

    /**
     * @since 1.0.12
     */
    private function path(string $channelKey, string $field): string
    {
        return sprintf('settings.alerts.%s.%s', $channelKey, $field);
    }

    /**
     * @since 1.0.12
     */
    private function booleanValue(mixed $value): bool
    {
        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($boolean) ? $boolean : false;
    }

    /**
     * @since 1.0.12
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

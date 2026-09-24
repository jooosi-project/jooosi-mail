<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;

/**
 * Resolves profile settings and shared routing settings for a connection.
 *
 * @since 1.0.9
 */
#[Service]
final class ConnectionProfileSettingsResolver
{
    public function __construct(
        private readonly ProfileMetadataResolver $profileMetadataResolver,
        private readonly ConnectionInputNormalizer $inputNormalizer,
        private readonly ConnectionSenderPolicyInputResolver $senderPolicyInputResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function resolve(MailProfileInterface $profile, array $input, ?Connection $existingConnection): array
    {
        $settings = $existingConnection?->settings ?? [];

        if ($existingConnection instanceof Connection && $existingConnection->profileKey !== $this->profileMetadataResolver->getKey($profile)) {
            unset($settings['profile']);
        }

        $jsonSettings = $this->inputNormalizer->decodeJsonArray($input, 'settings_json');

        if ($jsonSettings !== null) {
            $settings = array_replace_recursive($settings, $jsonSettings);
        }

        $profileSettings = is_array($settings['profile'] ?? null) ? $settings['profile'] : [];

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if ($this->inputNormalizer->isSecretField($field)) {
                unset($profileSettings[$fieldName]);
            }
        }

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if ($this->inputNormalizer->isSecretField($field) || ! array_key_exists($fieldName, $input)) {
                continue;
            }

            $value = $this->inputNormalizer->normalizeConfigurationValue($field, $input[$fieldName]);

            if ($value === null) {
                unset($profileSettings[$fieldName]);
                continue;
            }

            $profileSettings[$fieldName] = $value;
        }

        if ($profileSettings === []) {
            unset($settings['profile']);
        } else {
            $settings['profile'] = $profileSettings;
        }

        $rateLimits = [
            'minute' => $this->inputNormalizer->extractPositiveIntOrZero($input, 'rate_limit_minute'),
            'hour' => $this->inputNormalizer->extractPositiveIntOrZero($input, 'rate_limit_hour'),
            'day' => $this->inputNormalizer->extractPositiveIntOrZero($input, 'rate_limit_day'),
        ];

        foreach ($rateLimits as $period => $limit) {
            if ($limit === null) {
                continue;
            }

            $settings['rate_limits'][$period] = $limit;
        }

        $circuitBreaker = [
            'threshold' => $this->inputNormalizer->extractPositiveIntOrZero($input, 'circuit_threshold'),
            'window' => $this->inputNormalizer->extractPositiveIntOrZero($input, 'circuit_window'),
            'cooldown' => $this->inputNormalizer->extractPositiveIntOrZero($input, 'circuit_cooldown'),
        ];

        foreach ($circuitBreaker as $key => $value) {
            if ($value === null) {
                continue;
            }

            $settings['circuit_breaker'][$key] = $value;
        }

        return $this->senderPolicyInputResolver->resolve($input, $settings);
    }
}

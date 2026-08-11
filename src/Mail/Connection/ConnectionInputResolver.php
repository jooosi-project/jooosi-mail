<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;
use JooosiMail\Mail\Sender\SenderPolicyResolver;

/**
 * Resolves raw connection input into a connection value object.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionInputResolver
{
    public function __construct(
        private readonly ProfileMetadataResolver $profileMetadataResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    public function resolve(?Connection $existingConnection, MailProfileInterface $profile, array $input): Connection
    {
        $profileKey = $this->profileMetadataResolver->getKey($profile);
        $name = $this->resolveName($input, $existingConnection);
        $dsn = $this->resolveDsnOverride($profile, $input, $existingConnection);
        $settings = $this->resolveSettings($profile, $input, $existingConnection);
        $secrets = $this->resolveSecrets($profile, $input, $existingConnection);
        $enabled = $this->resolveBoolean($input, 'enabled', $existingConnection?->enabled ?? true);
        $default = $this->resolveBoolean($input, 'default', $existingConnection?->default ?? false);
        $priority = $this->resolveInt($input, 'priority', $existingConnection?->priority ?? 10, 1);
        $weight = $this->resolveInt($input, 'weight', $existingConnection?->weight ?? 1, 1);
        $webhookEnabled = $this->resolveBoolean($input, 'webhook_enabled', $existingConnection?->webhookEnabled ?? false);

        if ($name === '') {
            throw new ConnectionConfigurationException('Connection name is required.');
        }

        if ($webhookEnabled && $profile->supportsWebhooks() === false) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Profile "%s" does not support webhooks.', $profileKey));
        }

        return new Connection(
            id: $existingConnection?->id,
            profileKey: $profileKey,
            name: $name,
            dsn: $dsn,
            settings: $settings,
            secrets: $secrets,
            enabled: $enabled,
            default: $default,
            priority: $priority,
            weight: $weight,
            webhookEnabled: $webhookEnabled,
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function resolveName(array $input, ?Connection $existingConnection): string
    {
        $name = $input['name'] ?? $existingConnection?->name ?? '';

        return is_scalar($name) ? trim((string) $name) : '';
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function resolveDsnOverride(MailProfileInterface $profile, array $input, ?Connection $existingConnection): ?string
    {
        if (! array_key_exists('dsn', $input)) {
            if ($existingConnection instanceof Connection && $existingConnection->profileKey !== $this->profileMetadataResolver->getKey($profile)) {
                return null;
            }

            return $existingConnection?->dsn;
        }

        $dsn = $this->extractScalarString($input, 'dsn');

        return $dsn !== null && $dsn !== '' ? $dsn : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function resolveSettings(MailProfileInterface $profile, array $input, ?Connection $existingConnection): array
    {
        $settings = $existingConnection?->settings ?? [];

        if ($existingConnection instanceof Connection && $existingConnection->profileKey !== $this->profileMetadataResolver->getKey($profile)) {
            unset($settings['profile']);
        }

        $jsonSettings = $this->decodeJsonArray($input, 'settings_json');

        if ($jsonSettings !== null) {
            $settings = array_replace_recursive($settings, $jsonSettings);
        }

        $profileSettings = is_array($settings['profile'] ?? null) ? $settings['profile'] : [];

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if ($this->isSecretField($field)) {
                unset($profileSettings[$fieldName]);
            }
        }

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if ($this->isSecretField($field) || ! array_key_exists($fieldName, $input)) {
                continue;
            }

            $value = $this->normalizeConfigurationValue($field, $input[$fieldName]);

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
            'minute' => $this->extractPositiveIntOrZero($input, 'rate_limit_minute'),
            'hour' => $this->extractPositiveIntOrZero($input, 'rate_limit_hour'),
            'day' => $this->extractPositiveIntOrZero($input, 'rate_limit_day'),
        ];

        foreach ($rateLimits as $period => $limit) {
            if ($limit === null) {
                continue;
            }

            $settings['rate_limits'][$period] = $limit;
        }

        $circuitBreaker = [
            'threshold' => $this->extractPositiveIntOrZero($input, 'circuit_threshold'),
            'window' => $this->extractPositiveIntOrZero($input, 'circuit_window'),
            'cooldown' => $this->extractPositiveIntOrZero($input, 'circuit_cooldown'),
        ];

        foreach ($circuitBreaker as $key => $value) {
            if ($value === null) {
                continue;
            }

            $settings['circuit_breaker'][$key] = $value;
        }

        $settings = $this->resolveSenderSettings($input, $settings);

        return $settings;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function resolveSenderSettings(array $input, array $settings): array
    {
        if (! array_key_exists('sender', $input)) {
            return $settings;
        }

        $sender = is_array($input['sender']) ? $input['sender'] : [];
        $senderSettings = [];
        $email = $this->extractScalarString($sender, 'email');
        $name = $this->extractScalarString($sender, 'name');
        $returnPathEmail = $this->extractScalarString($sender, 'return_path_email');
        $forceEmail = $this->resolveBoolean($sender, 'force_email', false);
        $forceName = $this->resolveBoolean($sender, 'force_name', false);
        $returnPathMode = $this->normalizeSenderMode(
            $sender['return_path_mode'] ?? SenderPolicyResolver::RETURN_PATH_MODE_INHERIT,
            [
                SenderPolicyResolver::RETURN_PATH_MODE_INHERIT,
                SenderPolicyResolver::RETURN_PATH_MODE_PROVIDER_DEFAULT,
                SenderPolicyResolver::RETURN_PATH_MODE_MATCH_FROM,
                SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM,
            ],
            SenderPolicyResolver::RETURN_PATH_MODE_INHERIT,
            'Return-Path',
        );

        if ($email !== null && $email !== '') {
            if (! is_email($email)) {
                throw new ConnectionConfigurationException('Sender email must be a valid email address.');
            }

            $senderSettings['email'] = $email;
        }

        if ($name !== null && $name !== '') {
            $senderSettings['name'] = sanitize_text_field($name);
        }

        if ($forceEmail) {
            $senderSettings['force_email'] = true;
        }

        if ($forceName) {
            $senderSettings['force_name'] = true;
        }

        if ($returnPathMode !== SenderPolicyResolver::RETURN_PATH_MODE_INHERIT) {
            $senderSettings['return_path_mode'] = $returnPathMode;
        }

        if ($returnPathEmail !== null && $returnPathEmail !== '') {
            if (! is_email($returnPathEmail)) {
                throw new ConnectionConfigurationException('Return-Path email must be a valid email address.');
            }

            $senderSettings['return_path_email'] = $returnPathEmail;
        }

        if ($returnPathMode === SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM && ! isset($senderSettings['return_path_email'])) {
            throw new ConnectionConfigurationException('A custom Return-Path email address is required.');
        }

        if ($senderSettings === []) {
            unset($settings['sender']);
        } else {
            $settings['sender'] = $senderSettings;
        }

        return $settings;
    }

    /**
     * @param list<string> $allowedModes
     *
     * @since 0.1.0
     */
    private function normalizeSenderMode(mixed $value, array $allowedModes, string $defaultMode, string $label): string
    {
        if (! is_scalar($value)) {
            return $defaultMode;
        }

        $mode = strtolower(trim((string) $value));

        if (! in_array($mode, $allowedModes, true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('%s mode is not supported.', $label));
        }

        return $mode;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function resolveSecrets(MailProfileInterface $profile, array $input, ?Connection $existingConnection): array
    {
        $secrets = $existingConnection?->secrets ?? [];

        if ($existingConnection instanceof Connection && $existingConnection->profileKey !== $this->profileMetadataResolver->getKey($profile)) {
            unset($secrets['profile']);
        }

        $jsonSecrets = $this->decodeJsonArray($input, 'secrets_json');

        if ($jsonSecrets !== null) {
            $secrets = array_replace_recursive($secrets, $jsonSecrets);
        }

        $profileSecrets = is_array($secrets['profile'] ?? null) ? $secrets['profile'] : [];

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if (! $this->isSecretField($field)) {
                unset($profileSecrets[$fieldName]);
            }
        }

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if (! $this->isSecretField($field) || ! array_key_exists($fieldName, $input)) {
                continue;
            }

            $value = $this->normalizeConfigurationValue($field, $input[$fieldName]);

            if ($value === null) {
                unset($profileSecrets[$fieldName]);
                continue;
            }

            $profileSecrets[$fieldName] = (string) $value;
        }

        if ($profileSecrets === []) {
            unset($secrets['profile']);
        } else {
            $secrets['profile'] = $profileSecrets;
        }

        if (array_key_exists('webhook_secret', $input)) {
            $webhookSecret = $this->extractScalarString($input, 'webhook_secret');

            if ($webhookSecret === null || $webhookSecret === '') {
                unset($secrets['webhook_secret']);
            } else {
                $secrets['webhook_secret'] = $webhookSecret;
            }
        }

        return $secrets;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function resolveBoolean(array $input, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $input)) {
            return $default;
        }

        $value = $input[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function resolveInt(array $input, string $key, int $default, int $minimum = 0): int
    {
        if (! array_key_exists($key, $input)) {
            return $default;
        }

        return max($minimum, (int) $input[$key]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function extractPositiveIntOrZero(array $input, string $key): ?int
    {
        if (! array_key_exists($key, $input)) {
            return null;
        }

        return max(0, (int) $input[$key]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function extractScalarString(array $input, string $key): ?string
    {
        if (! array_key_exists($key, $input)) {
            return null;
        }

        $value = $input[$key];

        if (! is_scalar($value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $field
     *
     * @since 0.1.0
     */
    private function isSecretField(array $field): bool
    {
        return (($field['type'] ?? null) === 'password') || (($field['secret'] ?? false) === true);
    }

    /**
     * @param array<string, mixed> $field
     *
     * @since 0.1.0
     */
    private function normalizeConfigurationValue(array $field, mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return match ($field['type'] ?? null) {
            'number' => (int) $normalized,
            default => $normalized,
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     *
     * @since 0.1.0
     */
    private function decodeJsonArray(array $input, string $key): ?array
    {
        $value = $this->extractScalarString($input, $key);

        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Option "%s" must be valid JSON object data.', $key));
        }

        return $decoded;
    }
}

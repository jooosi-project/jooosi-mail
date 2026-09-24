<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

/**
 * Persisted mail connection settings.
 *
 * The optional `dsn` property stores a raw transport override. Canonical profile
 * configuration lives in `settings` and `secrets`, and profiles rebuild the
 * effective DSN lazily when delivery starts.
 *
 * @since 0.1.0
 */
final class Connection
{
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $secrets
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $profileKey,
        public readonly string $name,
        public readonly ?string $dsn = null,
        public readonly array $settings = [],
        public readonly array $secrets = [],
        public readonly bool $enabled = true,
        public readonly bool $default = false,
        public readonly int $priority = 10,
        public readonly int $weight = 1,
        public readonly bool $webhookEnabled = false,
    ) {
    }

    /**
     * Create an immutable copy with only the supplied fields changed.
     *
     * @param array{
     *     id?: int|null,
     *     profileKey?: string,
     *     name?: string,
     *     dsn?: string|null,
     *     settings?: array<string, mixed>,
     *     secrets?: array<string, mixed>,
     *     enabled?: bool,
     *     default?: bool,
     *     priority?: int,
     *     weight?: int,
     *     webhookEnabled?: bool
     * } $changes
     *
     * @since 1.0.9
     */
    public function with(array $changes): self
    {
        return new self(
            id: array_key_exists('id', $changes) ? $changes['id'] : $this->id,
            profileKey: array_key_exists('profileKey', $changes) ? $changes['profileKey'] : $this->profileKey,
            name: array_key_exists('name', $changes) ? $changes['name'] : $this->name,
            dsn: array_key_exists('dsn', $changes) ? $changes['dsn'] : $this->dsn,
            settings: array_key_exists('settings', $changes) ? $changes['settings'] : $this->settings,
            secrets: array_key_exists('secrets', $changes) ? $changes['secrets'] : $this->secrets,
            enabled: array_key_exists('enabled', $changes) ? $changes['enabled'] : $this->enabled,
            default: array_key_exists('default', $changes) ? $changes['default'] : $this->default,
            priority: array_key_exists('priority', $changes) ? $changes['priority'] : $this->priority,
            weight: array_key_exists('weight', $changes) ? $changes['weight'] : $this->weight,
            webhookEnabled: array_key_exists('webhookEnabled', $changes) ? $changes['webhookEnabled'] : $this->webhookEnabled,
        );
    }

    /**
     * Alias for callers that prefer copy semantics.
     *
     * @param array{
     *     id?: int|null,
     *     profileKey?: string,
     *     name?: string,
     *     dsn?: string|null,
     *     settings?: array<string, mixed>,
     *     secrets?: array<string, mixed>,
     *     enabled?: bool,
     *     default?: bool,
     *     priority?: int,
     *     weight?: int,
     *     webhookEnabled?: bool
     * } $changes
     *
     * @since 1.0.9
     */
    public function copy(array $changes = []): self
    {
        return $this->with($changes);
    }

    /**
     * @since 0.1.0
     */
    public function hasDsnOverride(): bool
    {
        return $this->dsn !== null && $this->dsn !== '';
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function getProfileSettings(): array
    {
        $settings = $this->settings['profile'] ?? null;

        return is_array($settings) ? $settings : [];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function getProfileSecrets(): array
    {
        $secrets = $this->secrets['profile'] ?? null;

        return is_array($secrets) ? $secrets : [];
    }

    /**
     * @since 0.1.0
     */
    public function getWebhookSecret(): ?string
    {
        $secret = $this->secrets['webhook_secret'] ?? null;

        if (! is_string($secret)) {
            return null;
        }

        $secret = trim($secret);

        return $secret !== '' ? $secret : null;
    }

    /**
     * @since 0.1.0
     */
    public function hasWebhookSecret(): bool
    {
        return $this->getWebhookSecret() !== null;
    }
}

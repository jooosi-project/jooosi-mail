<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;

/**
 * Resolves raw connection input into a connection value object.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionInputResolver
{
    private readonly ConnectionInputNormalizer $inputNormalizer;
    private readonly ConnectionProfileSettingsResolver $profileSettingsResolver;
    private readonly ConnectionProfileSecretsResolver $profileSecretsResolver;
    private readonly ConnectionSenderPolicyInputResolver $senderPolicyInputResolver;

    public function __construct(
        private readonly ProfileMetadataResolver $profileMetadataResolver,
        ?ConnectionInputNormalizer $inputNormalizer = null,
        ?ConnectionSenderPolicyInputResolver $senderPolicyInputResolver = null,
        ?ConnectionProfileSettingsResolver $profileSettingsResolver = null,
        ?ConnectionProfileSecretsResolver $profileSecretsResolver = null,
    ) {
        $this->inputNormalizer = $inputNormalizer ?? new ConnectionInputNormalizer();
        $this->senderPolicyInputResolver = $senderPolicyInputResolver ?? new ConnectionSenderPolicyInputResolver(
            $this->inputNormalizer,
        );
        $this->profileSettingsResolver = $profileSettingsResolver ?? new ConnectionProfileSettingsResolver(
            $this->profileMetadataResolver,
            $this->inputNormalizer,
            $this->senderPolicyInputResolver,
        );
        $this->profileSecretsResolver = $profileSecretsResolver ?? new ConnectionProfileSecretsResolver(
            $this->profileMetadataResolver,
            $this->inputNormalizer,
        );
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
        $settings = $this->profileSettingsResolver->resolve($profile, $input, $existingConnection);
        $secrets = $this->profileSecretsResolver->resolve($profile, $input, $existingConnection);
        $enabled = $this->inputNormalizer->resolveBoolean($input, 'enabled', $existingConnection?->enabled ?? true);
        $default = $this->inputNormalizer->resolveBoolean($input, 'default', $existingConnection?->default ?? false);
        $priority = $this->inputNormalizer->resolveInt($input, 'priority', $existingConnection?->priority ?? 10, 1);
        $weight = $this->inputNormalizer->resolveInt($input, 'weight', $existingConnection?->weight ?? 1, 1);
        $webhookEnabled = $this->inputNormalizer->resolveBoolean($input, 'webhook_enabled', $existingConnection?->webhookEnabled ?? false);

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

        $dsn = $this->inputNormalizer->extractScalarString($input, 'dsn');

        return $dsn !== null && $dsn !== '' ? $dsn : null;
    }
}

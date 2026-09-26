<?php

declare (strict_types=1);
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
    private readonly \JooosiMail\Mail\Connection\ConnectionInputNormalizer $inputNormalizer;
    private readonly \JooosiMail\Mail\Connection\ConnectionProfileSettingsResolver $profileSettingsResolver;
    private readonly \JooosiMail\Mail\Connection\ConnectionProfileSecretsResolver $profileSecretsResolver;
    private readonly \JooosiMail\Mail\Connection\ConnectionSenderPolicyInputResolver $senderPolicyInputResolver;
    public function __construct(private readonly ProfileMetadataResolver $profileMetadataResolver, ?\JooosiMail\Mail\Connection\ConnectionInputNormalizer $inputNormalizer = null, ?\JooosiMail\Mail\Connection\ConnectionSenderPolicyInputResolver $senderPolicyInputResolver = null, ?\JooosiMail\Mail\Connection\ConnectionProfileSettingsResolver $profileSettingsResolver = null, ?\JooosiMail\Mail\Connection\ConnectionProfileSecretsResolver $profileSecretsResolver = null)
    {
        $this->inputNormalizer = $inputNormalizer ?? new \JooosiMail\Mail\Connection\ConnectionInputNormalizer();
        $this->senderPolicyInputResolver = $senderPolicyInputResolver ?? new \JooosiMail\Mail\Connection\ConnectionSenderPolicyInputResolver($this->inputNormalizer);
        $this->profileSettingsResolver = $profileSettingsResolver ?? new \JooosiMail\Mail\Connection\ConnectionProfileSettingsResolver($this->profileMetadataResolver, $this->inputNormalizer, $this->senderPolicyInputResolver);
        $this->profileSecretsResolver = $profileSecretsResolver ?? new \JooosiMail\Mail\Connection\ConnectionProfileSecretsResolver($this->profileMetadataResolver, $this->inputNormalizer);
    }
    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    public function resolve(?\JooosiMail\Mail\Connection\Connection $existingConnection, MailProfileInterface $profile, array $input): \JooosiMail\Mail\Connection\Connection
    {
        $profileKey = $this->profileMetadataResolver->getKey($profile);
        $name = $this->resolveName($input, $existingConnection);
        $dsn = $this->resolveDsnOverride($profile, $input, $existingConnection);
        $settings = $this->profileSettingsResolver->resolve($profile, $input, $existingConnection);
        $secrets = $this->profileSecretsResolver->resolve($profile, $input, $existingConnection);
        $enabled = $this->inputNormalizer->resolveBoolean($input, 'enabled', $existingConnection?->enabled ?? \true);
        $default = $this->inputNormalizer->resolveBoolean($input, 'default', $existingConnection?->default ?? \false);
        $priority = $this->inputNormalizer->resolveInt($input, 'priority', $existingConnection?->priority ?? 10, 1);
        $weight = $this->inputNormalizer->resolveInt($input, 'weight', $existingConnection?->weight ?? 1, 1);
        $webhookEnabled = $this->inputNormalizer->resolveBoolean($input, 'webhook_enabled', $existingConnection?->webhookEnabled ?? \false);
        if ($name === '') {
            throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException('Connection name is required.');
        }
        if ($webhookEnabled && $profile->supportsWebhooks() === \false) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Profile "%s" does not support webhooks.', $profileKey));
        }
        return new \JooosiMail\Mail\Connection\Connection(id: $existingConnection?->id, profileKey: $profileKey, name: $name, dsn: $dsn, settings: $settings, secrets: $secrets, enabled: $enabled, default: $default, priority: $priority, weight: $weight, webhookEnabled: $webhookEnabled);
    }
    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function resolveName(array $input, ?\JooosiMail\Mail\Connection\Connection $existingConnection): string
    {
        $name = $input['name'] ?? $existingConnection?->name ?? '';
        return is_scalar($name) ? trim((string) $name) : '';
    }
    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function resolveDsnOverride(MailProfileInterface $profile, array $input, ?\JooosiMail\Mail\Connection\Connection $existingConnection): ?string
    {
        if (!array_key_exists('dsn', $input)) {
            if ($existingConnection instanceof \JooosiMail\Mail\Connection\Connection && $existingConnection->profileKey !== $this->profileMetadataResolver->getKey($profile)) {
                return null;
            }
            return $existingConnection?->dsn;
        }
        $dsn = $this->inputNormalizer->extractScalarString($input, 'dsn');
        return $dsn !== null && $dsn !== '' ? $dsn : null;
    }
}

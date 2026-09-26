<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionConfigurationException;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * AhaSend transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'ahasend', label: 'AhaSend', description: 'Send mail through AhaSend using the Symfony bridge API or SMTP transport.', website: 'https://ahasend.com', docsUrl: 'https://ahasend.com/docs', useCases: ['transactional'])]
final class AhaSendProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['ahasend+api', 'ahasend+smtp'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['received', 'delivered', 'deferred', 'failed', 'bounce', 'dropped', 'click', 'open'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'ahasend+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'AhaSend API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'ahasend+api')], 'required_when' => [$this->conditionIn('scheme', 'ahasend+api')]], 'username' => ['label' => 'AhaSend SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'ahasend+smtp')], 'required_when' => [$this->conditionIn('scheme', 'ahasend+smtp')]], 'password' => ['label' => 'AhaSend SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'ahasend+smtp')], 'required_when' => [$this->conditionIn('scheme', 'ahasend+smtp')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'ahasend+api';
        match ($scheme) {
            'ahasend+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'ahasend+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
        if ($connection->webhookEnabled && !$connection->hasWebhookSecret()) {
            throw new ConnectionConfigurationException('Webhook secret is required for profile "ahasend" when webhooks are enabled.');
        }
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'ahasend+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        return match ($scheme) {
            'ahasend+api' => $this->buildApiDsn($defaults),
            'ahasend+smtp' => $this->buildSmtpDsn($defaults),
            default => null,
        };
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildApiDsn(array $defaults): ?string
    {
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        return $apiKey === null || $apiKey === '' ? null : 'ahasend+api://' . rawurlencode($apiKey) . '@default';
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildSmtpDsn(array $defaults): ?string
    {
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if ($username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        return 'ahasend+smtp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

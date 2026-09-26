<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Brevo transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'brevo', label: 'Brevo', description: 'Send mail through Brevo using the Symfony bridge API or SMTP transport.', website: 'https://www.brevo.com', docsUrl: 'https://developers.brevo.com/', useCases: ['transactional', 'marketing'])]
final class BrevoProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['brevo+api', 'brevo+smtp'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['request', 'deferred', 'delivered', 'soft_bounce', 'hard_bounce', 'invalid_email', 'blocked', 'error', 'click', 'unsubscribed', 'unique_opened', 'opened', 'proxy_open', 'unique_proxy_open', 'complaint'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'brevo+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Brevo API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'brevo+api')], 'required_when' => [$this->conditionIn('scheme', 'brevo+api')]], 'username' => ['label' => 'Brevo SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'brevo+smtp')], 'required_when' => [$this->conditionIn('scheme', 'brevo+smtp')]], 'password' => ['label' => 'Brevo SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'brevo+smtp')], 'required_when' => [$this->conditionIn('scheme', 'brevo+smtp')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'brevo+api';
        match ($scheme) {
            'brevo+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'brevo+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'brevo+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        return match ($scheme) {
            'brevo+api' => $this->buildApiDsn($defaults),
            'brevo+smtp' => $this->buildSmtpDsn($defaults),
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
        $apiKey = is_string($defaults['api_key'] ?? null) ? trim((string) $defaults['api_key']) : null;
        if ($apiKey === null || $apiKey === '') {
            return null;
        }
        return 'brevo+api://' . rawurlencode($apiKey) . '@default';
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
        return 'brevo+smtp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

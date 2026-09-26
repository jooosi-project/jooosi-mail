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
 * Mailomat transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'mailomat', label: 'Mailomat', description: 'Send mail through Mailomat using the Symfony bridge API or SMTP transport.', website: 'https://mailomat.swiss', useCases: ['transactional'])]
final class MailomatProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['mailomat+api', 'mailomat+smtp'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['accepted', 'not_accepted', 'delivered', 'failure_tmp', 'failure_perm', 'opened', 'clicked'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'mailomat+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Mailomat API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailomat+api')], 'required_when' => [$this->conditionIn('scheme', 'mailomat+api')]], 'username' => ['label' => 'Mailomat SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailomat+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailomat+smtp')]], 'password' => ['label' => 'Mailomat SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailomat+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailomat+smtp')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailomat+api';
        match ($scheme) {
            'mailomat+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'mailomat+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
        if ($connection->webhookEnabled && !$connection->hasWebhookSecret()) {
            throw new ConnectionConfigurationException('Webhook secret is required for profile "mailomat" when webhooks are enabled.');
        }
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailomat+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        return match ($scheme) {
            'mailomat+api' => $this->buildApiDsn($defaults),
            'mailomat+smtp' => $this->buildSmtpDsn($defaults),
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
        return $apiKey === null || $apiKey === '' ? null : 'mailomat+api://' . rawurlencode($apiKey) . '@default';
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
        return 'mailomat+smtp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Mailgun transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'mailgun', label: 'Mailgun', description: 'Send mail through Mailgun using the Symfony bridge API, HTTP, or SMTP transport.', website: 'https://www.mailgun.com', docsUrl: 'https://documentation.mailgun.com/', useCases: ['transactional', 'marketing'])]
final class MailgunProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['mailgun+api', 'mailgun+https', 'mailgun+smtp'];
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['accepted', 'delivered', 'failed', 'opened', 'clicked', 'unsubscribed', 'complained', 'stored'];
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'mailgun+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Mailgun API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['mailgun+api', 'mailgun+https'])], 'required_when' => [$this->conditionIn('scheme', ['mailgun+api', 'mailgun+https'])]], 'domain' => ['label' => 'Mailgun domain', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['mailgun+api', 'mailgun+https'])], 'required_when' => [$this->conditionIn('scheme', ['mailgun+api', 'mailgun+https'])]], 'username' => ['label' => 'Mailgun SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailgun+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailgun+smtp')]], 'password' => ['label' => 'Mailgun SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailgun+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailgun+smtp')]], 'region' => ['label' => 'Mailgun region', 'type' => 'text', 'required' => \false]];
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailgun+api';
        match ($scheme) {
            'mailgun+api', 'mailgun+https' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key', 'domain']),
            'mailgun+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailgun+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        $authority = match ($scheme) {
            'mailgun+api', 'mailgun+https' => $this->buildApiAuthority($defaults),
            'mailgun+smtp' => $this->buildSmtpAuthority($defaults),
            default => null,
        };
        if ($authority === null || $authority === '') {
            return null;
        }
        $query = $this->buildQueryString(['region' => $this->extractScalarString($defaults, 'region')]);
        $dsn = $scheme . '://' . $authority;
        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildApiAuthority(array $defaults): ?string
    {
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        $domain = $this->extractScalarString($defaults, 'domain');
        if ($apiKey === null || $apiKey === '' || $domain === null || $domain === '') {
            return null;
        }
        return rawurlencode($apiKey) . ':' . rawurlencode($domain) . '@default';
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildSmtpAuthority(array $defaults): ?string
    {
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if ($username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        return rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

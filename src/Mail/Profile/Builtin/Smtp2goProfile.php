<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * SMTP2GO transport profile.
 *
 * @link https://developers.smtp2go.com/docs/send-an-email
 * @link https://developers.smtp2go.com/docs/smtp-relay
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'smtp2go', label: 'SMTP2GO', description: 'Send mail through SMTP2GO using custom API or SMTP transports.', website: 'https://www.smtp2go.com', docsUrl: 'https://developers.smtp2go.com/docs/send-an-email', useCases: ['transactional', 'marketing'])]
final class Smtp2goProfile extends AbstractMailProfile
{
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['processed', 'delivered', 'open', 'click', 'bounce', 'spam_complaint', 'unsubscribed', 'resubscribed', 'rejected'];
    }
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['smtp2go+api', 'smtp2go+smtp', 'smtp2go+smtps'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'smtp2go+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'SMTP2GO API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'smtp2go+api')], 'required_when' => [$this->conditionIn('scheme', 'smtp2go+api')]], 'username' => ['label' => 'SMTP2GO SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['smtp2go+smtp', 'smtp2go+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['smtp2go+smtp', 'smtp2go+smtps'])]], 'password' => ['label' => 'SMTP2GO SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['smtp2go+smtp', 'smtp2go+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['smtp2go+smtp', 'smtp2go+smtps'])]], 'region' => ['label' => 'SMTP2GO region', 'type' => 'choice', 'required' => \false, 'default' => 'global', 'choices' => ['global', 'us', 'eu', 'eu2', 'au']]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'smtp2go+api';
        match ($scheme) {
            'smtp2go+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'smtp2go+smtp', 'smtp2go+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'smtp2go+api';
        $region = $this->extractScalarString($defaults, 'region');
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        if ($scheme === 'smtp2go+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            if ($apiKey === null || $apiKey === '') {
                return null;
            }
            $query = $this->buildQueryString(['region' => $region]);
            $dsn = $scheme . '://' . rawurlencode($apiKey) . '@default';
            return $query === '' ? $dsn : $dsn . '?' . $query;
        }
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if ($username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        $query = $this->buildQueryString(['region' => $region]);
        $dsn = $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Mailjet transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'mailjet', label: 'Mailjet', description: 'Send mail through Mailjet using the Symfony bridge API or SMTP transport.', website: 'https://www.mailjet.com', docsUrl: 'https://dev.mailjet.com', useCases: ['transactional', 'marketing'])]
final class MailjetProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['mailjet+api', 'mailjet+smtp'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['bounce', 'sent', 'blocked', 'click', 'open', 'spam', 'unsub'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'mailjet+api', 'choices' => $this->getSupportedSchemes()], 'access_key' => ['label' => 'Mailjet API access key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailjet+api')], 'required_when' => [$this->conditionIn('scheme', 'mailjet+api')]], 'secret_key' => ['label' => 'Mailjet API secret key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailjet+api')], 'required_when' => [$this->conditionIn('scheme', 'mailjet+api')]], 'smtp_access_key' => ['label' => 'Mailjet SMTP username (API key)', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailjet+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailjet+smtp')]], 'smtp_secret_key' => ['label' => 'Mailjet SMTP password (secret key)', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailjet+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailjet+smtp')]], 'sandbox' => ['label' => 'Mailjet sandbox mode', 'type' => 'choice', 'required' => \false, 'default' => 'false', 'choices' => ['false', 'true'], 'visible_when' => [$this->conditionIn('scheme', 'mailjet+api')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailjet+api';
        match ($scheme) {
            'mailjet+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['access_key', 'secret_key']),
            'mailjet+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_access_key', 'smtp_secret_key']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailjet+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        $usesApi = $scheme === 'mailjet+api';
        $accessKeyField = $usesApi ? 'access_key' : 'smtp_access_key';
        $secretKeyField = $usesApi ? 'secret_key' : 'smtp_secret_key';
        $accessKey = is_string($defaults[$accessKeyField] ?? null) ? (string) $defaults[$accessKeyField] : null;
        $secretKey = is_string($defaults[$secretKeyField] ?? null) ? (string) $defaults[$secretKeyField] : null;
        if ($accessKey === null || $accessKey === '' || $secretKey === null || $secretKey === '') {
            return null;
        }
        $dsn = $scheme . '://' . rawurlencode($accessKey) . ':' . rawurlencode($secretKey) . '@default';
        if ($scheme !== 'mailjet+api') {
            return $dsn;
        }
        $sandbox = filter_var($defaults['sandbox'] ?? \false, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        $query = $sandbox === \true ? 'sandbox=true' : '';
        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
}

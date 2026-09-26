<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * MailerSend transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'mailersend', label: 'MailerSend', description: 'Send mail through MailerSend using the Symfony bridge API or SMTP transport.', website: 'https://www.mailersend.com', docsUrl: 'https://developers.mailersend.com', useCases: ['transactional'])]
final class MailerSendProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['mailersend+api', 'mailersend+smtp'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['activity_sent', 'activity_delivered', 'activity_soft_bounced', 'activity_hard_bounced', 'activity_clicked', 'activity_clicked_unique', 'activity_unsubscribed', 'activity_opened', 'activity_opened_unique', 'activity_spam_complaint'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'mailersend+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'MailerSend API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailersend+api')], 'required_when' => [$this->conditionIn('scheme', 'mailersend+api')]], 'username' => ['label' => 'MailerSend SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailersend+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailersend+smtp')]], 'password' => ['label' => 'MailerSend SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailersend+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailersend+smtp')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailersend+api';
        match ($scheme) {
            'mailersend+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'mailersend+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailersend+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        return match ($scheme) {
            'mailersend+api' => $this->buildApiDsn($defaults),
            'mailersend+smtp' => $this->buildSmtpDsn($defaults),
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
        return $apiKey === null || $apiKey === '' ? null : 'mailersend+api://' . rawurlencode($apiKey) . '@default';
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
        return 'mailersend+smtp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

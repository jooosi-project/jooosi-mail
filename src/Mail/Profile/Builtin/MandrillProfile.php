<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Mandrill transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'mandrill', label: 'Mandrill', description: 'Send mail through Mandrill using the Symfony bridge API, HTTP, or SMTP transport.', website: 'https://mailchimp.com/developer/transactional/', docsUrl: 'https://mailchimp.com/developer/transactional/api/messages/send-new-message/', useCases: ['transactional'])]
final class MandrillProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['mandrill+api', 'mandrill+https', 'mandrill+smtp'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['send', 'deferral', 'soft_bounce', 'hard_bounce', 'delivered', 'reject', 'click', 'open', 'spam', 'unsub'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'mandrill+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Mandrill API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['mandrill+api', 'mandrill+https'])], 'required_when' => [$this->conditionIn('scheme', ['mandrill+api', 'mandrill+https'])]], 'username' => ['label' => 'Mandrill SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mandrill+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mandrill+smtp')]], 'password' => ['label' => 'Mandrill SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mandrill+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mandrill+smtp')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mandrill+api';
        match ($scheme) {
            'mandrill+api', 'mandrill+https' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'mandrill+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mandrill+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        return match ($scheme) {
            'mandrill+api', 'mandrill+https' => $this->buildApiDsn($scheme, $defaults),
            'mandrill+smtp' => $this->buildSmtpDsn($defaults),
            default => null,
        };
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildApiDsn(string $scheme, array $defaults): ?string
    {
        $apiKey = is_string($defaults['api_key'] ?? null) ? trim((string) $defaults['api_key']) : null;
        return $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
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
        return 'mandrill+smtp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

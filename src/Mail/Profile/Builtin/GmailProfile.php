<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Gmail transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'gmail', label: 'Gmail', description: 'Send mail through Gmail using either app-password SMTP or a Google Workspace service account via the Gmail API.', website: 'https://workspace.google.com/products/gmail/', docsUrl: 'https://developers.google.com/gmail/api', useCases: ['transactional'])]
final class GmailProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['gmail+api', 'gmail+smtp'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'gmail+smtp', 'choices' => $this->getSupportedSchemes()], 'username' => ['label' => 'Gmail address', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'gmail+smtp')], 'required_when' => [$this->conditionIn('scheme', 'gmail+smtp')]], 'password' => ['label' => 'Gmail app password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'gmail+smtp')], 'required_when' => [$this->conditionIn('scheme', 'gmail+smtp')]], 'service_account_email' => ['label' => 'Google service account email', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'gmail+api')], 'required_when' => [$this->conditionIn('scheme', 'gmail+api')]], 'private_key' => ['label' => 'Google service account private key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'gmail+api')], 'required_when' => [$this->conditionIn('scheme', 'gmail+api')]], 'user_email' => ['label' => 'Delegated Gmail sender address', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'gmail+api')], 'required_when' => [$this->conditionIn('scheme', 'gmail+api')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'gmail+smtp';
        match ($scheme) {
            'gmail+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['service_account_email', 'private_key', 'user_email']),
            'gmail+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'gmail+smtp';
        return match ($scheme) {
            'gmail+api' => $this->buildApiDsn($defaults),
            'gmail+smtp' => $this->buildSmtpDsn($defaults),
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
        $serviceAccountEmail = $this->extractScalarString($defaults, 'service_account_email');
        $privateKey = is_string($defaults['private_key'] ?? null) ? (string) $defaults['private_key'] : null;
        $userEmail = $this->extractScalarString($defaults, 'user_email');
        if ($serviceAccountEmail === null || $serviceAccountEmail === '' || $privateKey === null || $privateKey === '' || $userEmail === null || $userEmail === '') {
            return null;
        }
        $dsn = 'gmail+api://' . rawurlencode($serviceAccountEmail) . ':' . rawurlencode(base64_encode($privateKey)) . '@default';
        $query = $this->buildQueryString(['user' => $userEmail]);
        return $query === '' ? $dsn : $dsn . '?' . $query;
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
        return 'gmail+smtp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

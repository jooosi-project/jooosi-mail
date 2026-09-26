<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Amazon SES transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'ses', label: 'Amazon SES', description: 'Send mail through Amazon SES using the Symfony bridge API, HTTP, or SMTP transport.', website: 'https://aws.amazon.com/ses/', docsUrl: 'https://docs.aws.amazon.com/ses/', useCases: ['transactional', 'marketing'])]
final class SesProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['ses+api', 'ses+https', 'ses+smtp'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'ses+api', 'choices' => $this->getSupportedSchemes()], 'access_key' => ['label' => 'Amazon SES access key ID', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['ses+api', 'ses+https'])], 'required_when' => [$this->conditionIn('scheme', ['ses+api', 'ses+https'])]], 'secret_key' => ['label' => 'Amazon SES secret access key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['ses+api', 'ses+https'])], 'required_when' => [$this->conditionIn('scheme', ['ses+api', 'ses+https'])]], 'username' => ['label' => 'Amazon SES SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'ses+smtp')], 'required_when' => [$this->conditionIn('scheme', 'ses+smtp')]], 'password' => ['label' => 'Amazon SES SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'ses+smtp')], 'required_when' => [$this->conditionIn('scheme', 'ses+smtp')]], 'region' => ['label' => 'Amazon SES region', 'type' => 'text', 'required' => \false], 'session_token' => ['label' => 'Amazon SES session token', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['ses+api', 'ses+https'])]]];
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'ses+api';
        match ($scheme) {
            'ses+api', 'ses+https' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['access_key', 'secret_key']),
            'ses+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
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
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'ses+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        $authority = match ($scheme) {
            'ses+api', 'ses+https' => $this->buildApiAuthority($defaults),
            'ses+smtp' => $this->buildSmtpAuthority($defaults),
            default => null,
        };
        if ($authority === null || $authority === '') {
            return null;
        }
        $usesApi = in_array($scheme, ['ses+api', 'ses+https'], \true);
        $query = $this->buildQueryString(['region' => $this->extractScalarString($defaults, 'region'), 'session_token' => $usesApi && is_string($defaults['session_token'] ?? null) ? (string) $defaults['session_token'] : null]);
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
        $accessKey = is_string($defaults['access_key'] ?? null) ? (string) $defaults['access_key'] : null;
        $secretKey = is_string($defaults['secret_key'] ?? null) ? (string) $defaults['secret_key'] : null;
        if ($accessKey === null || $accessKey === '' || $secretKey === null || $secretKey === '') {
            return null;
        }
        return rawurlencode($accessKey) . ':' . rawurlencode($secretKey) . '@default';
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

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * SMTP.com transport profile.
 *
 * @link https://www.smtp.com/resources/api-documentation/
 * @link https://knowledge.smtp.com/s/article/Supported-Ports-Settings
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'smtpcom', label: 'SMTP.com', description: 'Send mail through SMTP.com using custom API or SMTP transports.', website: 'https://www.smtp.com', docsUrl: 'https://www.smtp.com/resources/api-documentation/', useCases: ['transactional', 'marketing'])]
final class SmtpComProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['smtpcom+api', 'smtpcom+smtp', 'smtpcom+smtps'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'smtpcom+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'SMTP.com API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'smtpcom+api')], 'required_when' => [$this->conditionIn('scheme', 'smtpcom+api')]], 'username' => ['label' => 'SMTP.com SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['smtpcom+smtp', 'smtpcom+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['smtpcom+smtp', 'smtpcom+smtps'])]], 'password' => ['label' => 'SMTP.com SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['smtpcom+smtp', 'smtpcom+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['smtpcom+smtp', 'smtpcom+smtps'])]], 'channel' => ['label' => 'SMTP.com API channel', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'smtpcom+api')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'smtpcom+api';
        match ($scheme) {
            'smtpcom+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'smtpcom+smtp', 'smtpcom+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'smtpcom+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        if ($scheme === 'smtpcom+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            if ($apiKey === null || $apiKey === '') {
                return null;
            }
            $query = $this->buildQueryString(['channel' => $this->extractScalarString($defaults, 'channel')]);
            $dsn = $scheme . '://' . rawurlencode($apiKey) . '@default';
            return $query === '' ? $dsn : $dsn . '?' . $query;
        }
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if ($username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        return $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

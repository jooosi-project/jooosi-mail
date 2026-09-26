<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Pepipost transport profile.
 *
 * @link https://emaildocs.netcorecloud.com/reference/send-mail-api-1
 * @link https://emaildocs.netcorecloud.com/docs/how-to-integrate-your-mail-clients
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'pepipost', label: 'Pepipost', description: 'Send mail through Pepipost using custom API or SMTP transports.', website: 'https://netcorecloud.com/email/', docsUrl: 'https://emaildocs.netcorecloud.com/reference/send-mail-api-1', useCases: ['transactional', 'marketing'])]
final class PepipostProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['pepipost+api', 'pepipost+smtp', 'pepipost+smtps'];
    }
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'pepipost+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Pepipost API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'pepipost+api')], 'required_when' => [$this->conditionIn('scheme', 'pepipost+api')]], 'username' => ['label' => 'Pepipost SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['pepipost+smtp', 'pepipost+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['pepipost+smtp', 'pepipost+smtps'])]], 'password' => ['label' => 'Pepipost SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['pepipost+smtp', 'pepipost+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['pepipost+smtp', 'pepipost+smtps'])]], 'region' => ['label' => 'Pepipost region', 'type' => 'choice', 'required' => \false, 'choices' => ['eu'], 'visible_when' => [$this->conditionIn('scheme', 'pepipost+api')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'pepipost+api';
        match ($scheme) {
            'pepipost+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'pepipost+smtp', 'pepipost+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'pepipost+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        if ($scheme === 'pepipost+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            if ($apiKey === null || $apiKey === '') {
                return null;
            }
            $query = $this->buildQueryString(['region' => $this->extractScalarString($defaults, 'region')]);
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

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Elastic Email transport profile.
 *
 * @link https://elasticemail.com/developers/api-documentation/web-api-v2
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'elasticemail', label: 'Elastic Email', description: 'Send mail through Elastic Email using custom API or SMTP transports.', website: 'https://elasticemail.com', docsUrl: 'https://elasticemail.com/developers/api-documentation', useCases: ['transactional', 'marketing'])]
final class ElasticEmailProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['elasticemail+api', 'elasticemail+smtp', 'elasticemail+smtps'];
    }
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'elasticemail+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Elastic Email API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'elasticemail+api')], 'required_when' => [$this->conditionIn('scheme', 'elasticemail+api')]], 'username' => ['label' => 'Elastic Email SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['elasticemail+smtp', 'elasticemail+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['elasticemail+smtp', 'elasticemail+smtps'])]], 'password' => ['label' => 'Elastic Email SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['elasticemail+smtp', 'elasticemail+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['elasticemail+smtp', 'elasticemail+smtps'])]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'elasticemail+api';
        match ($scheme) {
            'elasticemail+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'elasticemail+smtp', 'elasticemail+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'elasticemail+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        if ($scheme === 'elasticemail+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            return $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
        }
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if ($username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        return $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

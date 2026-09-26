<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Infobip transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'infobip', label: 'Infobip', description: 'Send mail through Infobip using the Symfony bridge API or SMTP transport.', website: 'https://www.infobip.com', docsUrl: 'https://www.infobip.com/docs/email', useCases: ['transactional', 'marketing'])]
final class InfobipProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['infobip+api', 'infobip+smtp'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'infobip+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Infobip API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'infobip+api')], 'required_when' => [$this->conditionIn('scheme', 'infobip+api')]], 'smtp_api_key' => ['label' => 'Infobip SMTP API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'infobip+smtp')], 'required_when' => [$this->conditionIn('scheme', 'infobip+smtp')]], 'host' => ['label' => 'Infobip API host', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'infobip+api')], 'required_when' => [$this->conditionIn('scheme', 'infobip+api')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'infobip+api';
        match ($scheme) {
            'infobip+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key', 'host']),
            'infobip+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_api_key']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'infobip+api';
        $usesApi = $scheme === 'infobip+api';
        $apiKey = $this->extractScalarString($defaults, $usesApi ? 'api_key' : 'smtp_api_key');
        if (!in_array($scheme, $this->getSupportedSchemes(), \true) || $apiKey === null || $apiKey === '') {
            return null;
        }
        if ($scheme === 'infobip+smtp') {
            return 'infobip+smtp://' . rawurlencode($apiKey) . '@default';
        }
        $host = $this->extractScalarString($defaults, 'host');
        if ($host === null || $host === '') {
            return null;
        }
        return 'infobip+api://' . rawurlencode($apiKey) . '@' . $host;
    }
}

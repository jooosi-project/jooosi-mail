<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * SendPulse transport profile.
 *
 * @link https://sendpulse.com/integrations/api/smtp
 * @link https://sendpulse.com/knowledge-base/smtp
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'sendpulse', label: 'SendPulse', description: 'Send mail through SendPulse using custom API or SMTP transports.', website: 'https://sendpulse.com', docsUrl: 'https://sendpulse.com/integrations/api/smtp', useCases: ['transactional', 'marketing'])]
final class SendPulseProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sendpulse+api', 'sendpulse+smtp', 'sendpulse+smtps'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'sendpulse+api', 'choices' => $this->getSupportedSchemes()], 'client_id' => ['label' => 'SendPulse API client ID', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'sendpulse+api')], 'required_when' => [$this->conditionIn('scheme', 'sendpulse+api')]], 'client_secret' => ['label' => 'SendPulse API client secret', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'sendpulse+api')], 'required_when' => [$this->conditionIn('scheme', 'sendpulse+api')]], 'username' => ['label' => 'SendPulse SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['sendpulse+smtp', 'sendpulse+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['sendpulse+smtp', 'sendpulse+smtps'])]], 'password' => ['label' => 'SendPulse SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['sendpulse+smtp', 'sendpulse+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['sendpulse+smtp', 'sendpulse+smtps'])]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sendpulse+api';
        match ($scheme) {
            'sendpulse+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['client_id', 'client_secret']),
            'sendpulse+smtp', 'sendpulse+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sendpulse+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        if ($scheme === 'sendpulse+api') {
            $clientId = $this->extractScalarString($defaults, 'client_id');
            $clientSecret = is_string($defaults['client_secret'] ?? null) ? (string) $defaults['client_secret'] : null;
            if ($clientId === null || $clientId === '' || $clientSecret === null || $clientSecret === '') {
                return null;
            }
            return $scheme . '://' . rawurlencode($clientId) . ':' . rawurlencode($clientSecret) . '@default';
        }
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if ($username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        return $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

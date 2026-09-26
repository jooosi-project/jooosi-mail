<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * TurboSMTP transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'turbosmtp', label: 'TurboSMTP', description: 'Send mail through TurboSMTP using its REST API or SMTP relay.', website: 'https://www.turbo-smtp.com', useCases: ['transactional', 'marketing'])]
final class TurboSmtpProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['turbosmtp+api', 'turbosmtp+smtp'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        $smtpSchemes = ['turbosmtp+smtp'];
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'turbosmtp+api', 'choices' => $this->getSupportedSchemes()], 'consumer_key' => ['label' => 'TurboSMTP consumer key', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'turbosmtp+api')], 'required_when' => [$this->conditionIn('scheme', 'turbosmtp+api')]], 'consumer_secret' => ['label' => 'TurboSMTP consumer secret', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'turbosmtp+api')], 'required_when' => [$this->conditionIn('scheme', 'turbosmtp+api')]], 'smtp_username' => ['label' => 'TurboSMTP SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $smtpSchemes)], 'required_when' => [$this->conditionIn('scheme', $smtpSchemes)]], 'smtp_password' => ['label' => 'TurboSMTP SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $smtpSchemes)], 'required_when' => [$this->conditionIn('scheme', $smtpSchemes)]], 'region' => ['label' => 'TurboSMTP region', 'type' => 'choice', 'required' => \false, 'default' => 'global', 'choices' => ['global', 'eu']]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'turbosmtp+api';
        match ($scheme) {
            'turbosmtp+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['consumer_key', 'consumer_secret']),
            'turbosmtp+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_username', 'smtp_password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'turbosmtp+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        $usesApi = $scheme === 'turbosmtp+api';
        $username = $this->extractScalarString($defaults, $usesApi ? 'consumer_key' : 'smtp_username');
        $password = $this->extractScalarString($defaults, $usesApi ? 'consumer_secret' : 'smtp_password');
        if ($username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        $region = $this->extractScalarString($defaults, 'region') ?? 'global';
        $host = match ([$scheme, $region]) {
            ['turbosmtp+api', 'eu'] => 'api.eu.turbo-smtp.com',
            ['turbosmtp+smtp', 'eu'] => 'pro.eu.turbo-smtp.com',
            default => 'default',
        };
        return $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@' . $host;
    }
}

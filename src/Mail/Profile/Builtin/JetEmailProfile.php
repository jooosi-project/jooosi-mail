<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * JetEmail profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'jetemail', label: 'JetEmail', description: 'Send email through the JetEmail API or SMTP relay.', website: 'https://jetemail.com', docsUrl: 'https://jetemail.com/docs/api-reference/email/send-an-email', useCases: ['transactional'])]
final class JetEmailProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['jetemail+api', 'jetemail+smtp', 'jetemail+smtps'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        $smtpSchemes = ['jetemail+smtp', 'jetemail+smtps'];
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'jetemail+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'JetEmail API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'jetemail+api')], 'required_when' => [$this->conditionIn('scheme', 'jetemail+api')]], 'smtp_username' => ['label' => 'JetEmail SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $smtpSchemes)], 'required_when' => [$this->conditionIn('scheme', $smtpSchemes)]], 'smtp_password' => ['label' => 'JetEmail SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $smtpSchemes)], 'required_when' => [$this->conditionIn('scheme', $smtpSchemes)]], 'region' => ['label' => 'Delivery region', 'type' => 'choice', 'required' => \false, 'default' => 'global', 'choices' => ['global', 'eu'], 'visible_when' => [$this->conditionIn('scheme', $this->getSupportedSchemes())]]];
    }
    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'jetemail+api';
        match ($scheme) {
            'jetemail+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'jetemail+smtp', 'jetemail+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_username', 'smtp_password']),
            default => null,
        };
    }
    /**
     * @since 1.0.13
     */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'jetemail+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        $region = $this->extractScalarString($defaults, 'region') ?? 'global';
        $query = $this->buildQueryString(['region' => $region]);
        if ($scheme === 'jetemail+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            return $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default' . ($query === '' ? '' : '?' . $query);
        }
        $username = $this->extractScalarString($defaults, 'smtp_username');
        $password = $this->extractScalarString($defaults, 'smtp_password');
        return $username === null || $password === null || $password === '' ? null : $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default' . ($query === '' ? '' : '?' . $query);
    }
}

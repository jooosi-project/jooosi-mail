<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Emailit transport profile.
 *
 * @link https://docs.emailit.com/emails
 * @link https://docs.emailit.com/guides/sending-using-smtp
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'emailit', label: 'Emailit', description: 'Send mail through Emailit using custom API or SMTP transports.', website: 'https://emailit.com', docsUrl: 'https://docs.emailit.com', useCases: ['transactional'])]
final class EmailitProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['emailit+api', 'emailit+smtp', 'emailit+smtps'];
    }
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'emailit+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Emailit API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'emailit+api')], 'required_when' => [$this->conditionIn('scheme', 'emailit+api')]], 'smtp_credential' => ['label' => 'Emailit SMTP API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['emailit+smtp', 'emailit+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['emailit+smtp', 'emailit+smtps'])]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'emailit+api';
        match ($scheme) {
            'emailit+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'emailit+smtp', 'emailit+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_credential']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'emailit+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        if ($scheme === 'emailit+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            return $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
        }
        $smtpCredential = is_string($defaults['smtp_credential'] ?? null) ? (string) $defaults['smtp_credential'] : null;
        if ($smtpCredential === null || $smtpCredential === '') {
            return null;
        }
        return $scheme . '://emailit:' . rawurlencode($smtpCredential) . '@default';
    }
}

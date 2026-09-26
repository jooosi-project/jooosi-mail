<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * MailPace transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(
    key: 'mailpace',
    label: 'MailPace',
    description: 'Send mail through MailPace using the Symfony bridge API or SMTP transport.',
    website: 'https://mailpace.com',
    docsUrl: 'https://docs.mailpace.com',
    useCases: ['transactional'],
)]
final class MailPaceProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['mailpace+api', 'mailpace+smtp'];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'mailpace+api', 'choices' => $this->getSupportedSchemes()],
            'api_token' => ['label' => 'MailPace API token', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'mailpace+api')], 'required_when' => [$this->conditionIn('scheme', 'mailpace+api')]],
            'smtp_api_token' => ['label' => 'MailPace SMTP API token', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'mailpace+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailpace+smtp')]],
        ];
    }

    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailpace+api';

        match ($scheme) {
            'mailpace+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_token']),
            'mailpace+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_api_token']),
            default => null,
        };
    }

    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailpace+api';
        $tokenField = $scheme === 'mailpace+api' ? 'api_token' : 'smtp_api_token';
        $apiToken = is_string($defaults[$tokenField] ?? null) ? trim((string) $defaults[$tokenField]) : null;

        if (! in_array($scheme, $this->getSupportedSchemes(), true) || $apiToken === null || $apiToken === '') {
            return null;
        }

        return $scheme . '://' . rawurlencode($apiToken) . '@default';
    }
}

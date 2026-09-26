<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * MailKite transport profile.
 *
 * @link https://mailkite.dev/docs/sending/
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(
    key: 'mailkite',
    label: 'MailKite',
    description: 'Send mail through MailKite using its API or SMTP relay.',
    website: 'https://mailkite.dev',
    docsUrl: 'https://mailkite.dev/docs/sending/',
    useCases: ['transactional', 'marketing'],
)]
final class MailKiteProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['mailkite+api', 'mailkite+smtp', 'mailkite+smtps'];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        $smtpSchemes = ['mailkite+smtp', 'mailkite+smtps'];

        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'mailkite+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => [
                'label' => 'MailKite API key',
                'type' => 'password',
                'required' => false,
                'visible_when' => [$this->conditionIn('scheme', 'mailkite+api')],
                'required_when' => [$this->conditionIn('scheme', 'mailkite+api')],
            ],
            'smtp_username' => [
                'label' => 'MailKite SMTP username',
                'type' => 'text',
                'required' => false,
                'default' => 'mailkite',
                'visible_when' => [$this->conditionIn('scheme', $smtpSchemes)],
            ],
            'smtp_password' => [
                'label' => 'MailKite SMTP password',
                'type' => 'password',
                'required' => false,
                'visible_when' => [$this->conditionIn('scheme', $smtpSchemes)],
                'required_when' => [$this->conditionIn('scheme', $smtpSchemes)],
            ],
        ];
    }

    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailkite+api';

        match ($scheme) {
            'mailkite+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'mailkite+smtp', 'mailkite+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_username', 'smtp_password']),
            default => null,
        };
    }

    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailkite+api';

        if (! in_array($scheme, $this->getSupportedSchemes(), true)) {
            return null;
        }

        if ($scheme === 'mailkite+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');

            return $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
        }

        $username = $this->extractScalarString($defaults, 'smtp_username');
        $password = $this->extractScalarString($defaults, 'smtp_password');

        return $username === null || $password === null
            ? null
            : $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

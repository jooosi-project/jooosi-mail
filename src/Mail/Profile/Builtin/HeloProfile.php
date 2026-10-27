<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * Helo transactional email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'helo', label: 'Helo', description: 'Send email through the Helo API or SMTP relay.', website: 'https://helohq.com', docsUrl: 'https://docs.helohq.com/getting-started/quickstart-send-with-api', useCases: ['transactional'])]
final class HeloProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['helo+api', 'helo+smtp', 'helo+smtps'];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        $smtpSchemes = ['helo+smtp', 'helo+smtps'];

        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'helo+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => [
                'label' => 'Helo API key',
                'type' => 'password',
                'required' => false,
                'visible_when' => [$this->conditionIn('scheme', 'helo+api')],
                'required_when' => [$this->conditionIn('scheme', 'helo+api')],
            ],
            'channel_id' => [
                'label' => 'Channel ID (optional)',
                'type' => 'text',
                'required' => false,
                'visible_when' => [$this->conditionIn('scheme', 'helo+api')],
            ],
            'smtp_username' => [
                'label' => 'Helo SMTP user ID',
                'type' => 'text',
                'required' => false,
                'visible_when' => [$this->conditionIn('scheme', $smtpSchemes)],
                'required_when' => [$this->conditionIn('scheme', $smtpSchemes)],
            ],
            'smtp_password' => [
                'label' => 'Helo SMTP user password',
                'type' => 'password',
                'required' => false,
                'visible_when' => [$this->conditionIn('scheme', $smtpSchemes)],
                'required_when' => [$this->conditionIn('scheme', $smtpSchemes)],
            ],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'helo+api';
        match ($scheme) {
            'helo+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'helo+smtp', 'helo+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_username', 'smtp_password']),
            default => null,
        };
    }

    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'helo+api';
        if (! in_array($scheme, $this->getSupportedSchemes(), true)) {
            return null;
        }

        if ($scheme === 'helo+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            if ($apiKey === null || $apiKey === '') {
                return null;
            }

            $query = $this->buildQueryString(['channel_id' => $this->extractScalarString($defaults, 'channel_id')]);

            return 'helo+api://' . rawurlencode($apiKey) . '@default' . ($query === '' ? '' : '?' . $query);
        }

        $username = $this->extractScalarString($defaults, 'smtp_username');
        $password = $this->extractScalarString($defaults, 'smtp_password');

        return $username === null || $password === null || $password === ''
            ? null
            : $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

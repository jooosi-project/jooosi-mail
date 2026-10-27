<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * Unosend email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'unosend', label: 'Unosend', description: 'Send email through the Unosend API or SMTP relay.', website: 'https://unosend.co', docsUrl: 'https://docs.unosend.co/sdks/php', useCases: ['transactional'])]
final class UnosendProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['unosend+api', 'unosend+smtp', 'unosend+smtps'];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'unosend+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => [
                'label' => 'Unosend API key / SMTP password',
                'type' => 'password',
                'required' => false,
                'visible_when' => [$this->conditionIn('scheme', $this->getSupportedSchemes())],
                'required_when' => [$this->conditionIn('scheme', $this->getSupportedSchemes())],
            ],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'unosend+api';
        if (in_array($scheme, $this->getSupportedSchemes(), true)) {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }

    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'unosend+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');

        if (! in_array($scheme, $this->getSupportedSchemes(), true) || $apiKey === null || $apiKey === '') {
            return null;
        }

        return $scheme === 'unosend+api'
            ? $scheme . '://' . rawurlencode($apiKey) . '@default'
            : $scheme . '://emailapikey:' . rawurlencode($apiKey) . '@default';
    }
}

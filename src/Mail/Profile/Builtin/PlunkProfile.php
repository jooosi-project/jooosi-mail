<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * Plunk email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'plunk', label: 'Plunk', description: 'Send email through the Plunk API or SMTP relay.', website: 'https://useplunk.com', docsUrl: 'https://docs.useplunk.com/api-reference/public-api/sendEmail', useCases: ['transactional'])]
final class PlunkProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['plunk+api', 'plunk+smtp', 'plunk+smtps'];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'plunk+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => [
                'label' => 'Plunk API key / SMTP password',
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
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'plunk+api';
        if (in_array($scheme, $this->getSupportedSchemes(), true)) {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }

    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'plunk+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');

        if (! in_array($scheme, $this->getSupportedSchemes(), true) || $apiKey === null || $apiKey === '') {
            return null;
        }

        return $scheme === 'plunk+api'
            ? $scheme . '://' . rawurlencode($apiKey) . '@default'
            : $scheme . '://plunk:' . rawurlencode($apiKey) . '@default';
    }
}

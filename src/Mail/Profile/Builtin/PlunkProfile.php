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
#[MailProfile(key: 'plunk', label: 'Plunk', description: 'Send email through the Plunk API.', website: 'https://useplunk.com', docsUrl: 'https://docs.useplunk.com/api-reference/public-api/sendEmail', useCases: ['transactional'])]
final class PlunkProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['plunk+api'];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'plunk+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => ['label' => 'Plunk API key', 'type' => 'password', 'required' => true],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'plunk+api';
        if ($scheme === 'plunk+api') {
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

        return $scheme !== 'plunk+api' || $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * Lettr email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'lettr', label: 'Lettr', description: 'Send email through the Lettr API.', website: 'https://lettr.com', docsUrl: 'https://docs.lettr.com/api-reference/emails/send-email', useCases: ['transactional'])]
final class LettrProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['lettr+api'];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'lettr+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => ['label' => 'Lettr API key', 'type' => 'password', 'required' => true],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'lettr+api';
        if ($scheme === 'lettr+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }

    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'lettr+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');

        return $scheme !== 'lettr+api' || $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
    }
}

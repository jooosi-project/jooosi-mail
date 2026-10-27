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
#[MailProfile(key: 'lettr', label: 'Lettr', description: 'Send email through the Lettr API or SMTP relay.', website: 'https://lettr.com', docsUrl: 'https://docs.lettr.com/api-reference/emails/send-email', useCases: ['transactional'])]
final class LettrProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['lettr+api', 'lettr+smtp', 'lettr+smtps'];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'lettr+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => [
                'label' => 'Lettr API key / SMTP password',
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
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'lettr+api';
        if (in_array($scheme, $this->getSupportedSchemes(), true)) {
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

        if (! in_array($scheme, $this->getSupportedSchemes(), true) || $apiKey === null || $apiKey === '') {
            return null;
        }

        $username = $scheme === 'lettr+api' ? rawurlencode($apiKey) : 'lettr';
        $password = $scheme === 'lettr+api' ? '' : ':' . rawurlencode($apiKey);

        return $scheme . '://' . $username . $password . '@default';
    }
}

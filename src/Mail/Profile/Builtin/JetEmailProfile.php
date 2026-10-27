<?php

declare(strict_types=1);

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
#[MailProfile(key: 'jetemail', label: 'JetEmail', description: 'Send email through the JetEmail API.', website: 'https://jetemail.com', docsUrl: 'https://jetemail.com/docs/api-reference/email/send-an-email', useCases: ['transactional'])]
final class JetEmailProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['jetemail+api'];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'jetemail+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => ['label' => 'JetEmail API key', 'type' => 'password', 'required' => true],
            'region' => ['label' => 'Delivery region', 'type' => 'choice', 'required' => false, 'default' => 'global', 'choices' => ['global', 'eu']],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'jetemail+api';
        if ($scheme === 'jetemail+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }

    /**
     * @since 1.0.13
     */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'jetemail+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        if ($scheme !== 'jetemail+api' || $apiKey === null || $apiKey === '') {
            return null;
        }

        $query = $this->buildQueryString(['region' => $this->extractScalarString($defaults, 'region') ?? 'global']);

        return 'jetemail+api://' . rawurlencode($apiKey) . '@default' . ($query === '' ? '' : '?' . $query);
    }
}

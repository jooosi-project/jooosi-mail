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
#[MailProfile(key: 'helo', label: 'Helo', description: 'Send transactional email through the Helo API.', website: 'https://helohq.com', docsUrl: 'https://docs.helohq.com/getting-started/quickstart-send-with-api', useCases: ['transactional'])]
final class HeloProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['helo+api'];
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
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'helo+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => ['label' => 'Helo API key', 'type' => 'password', 'required' => true],
            'channel_id' => ['label' => 'Channel ID (optional)', 'type' => 'text', 'required' => false],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'helo+api';
        if ($scheme === 'helo+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }

    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'helo+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        if ($scheme !== 'helo+api' || $apiKey === null || $apiKey === '') {
            return null;
        }

        $query = $this->buildQueryString(['channel_id' => $this->extractScalarString($defaults, 'channel_id')]);

        return 'helo+api://' . rawurlencode($apiKey) . '@default' . ($query === '' ? '' : '?' . $query);
    }
}

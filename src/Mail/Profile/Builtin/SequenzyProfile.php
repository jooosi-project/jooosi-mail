<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * Sequenzy transactional email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'sequenzy', label: 'Sequenzy', description: 'Send transactional mail through the Sequenzy API.', website: 'https://www.sequenzy.com', docsUrl: 'https://docs.sequenzy.com/api-reference/transactional/send', useCases: ['transactional'])]
final class SequenzyProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sequenzy+api'];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'sequenzy+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => ['label' => 'Sequenzy API key', 'type' => 'password', 'required' => true],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sequenzy+api';
        if ($scheme === 'sequenzy+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }

    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sequenzy+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');

        return $scheme !== 'sequenzy+api' || $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
    }
}

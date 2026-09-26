<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * PufferPost transport profile.
 *
 * @link https://docs.pufferpost.com/guides/sending/
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'pufferpost', label: 'PufferPost', description: 'Send mail through PufferPost using its REST API.', website: 'https://pufferpost.com', docsUrl: 'https://docs.pufferpost.com/guides/sending/', useCases: ['transactional'])]
final class PufferPostProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['pufferpost+api'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'pufferpost+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'PufferPost API key', 'type' => 'password', 'required' => \true]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'pufferpost+api';
        if (in_array($scheme, $this->getSupportedSchemes(), \true)) {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'pufferpost+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        return $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
    }
}

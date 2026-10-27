<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * SendHeron email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'sendheron', label: 'SendHeron', description: 'Send email through the SendHeron API.', website: 'https://sendheron.com', docsUrl: 'https://sendheron.com/docs/emails/send-email', useCases: ['transactional'])]
final class SendHeronProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sendheron+api'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'sendheron+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'SendHeron API key', 'type' => 'password', 'required' => \true]];
    }
    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sendheron+api';
        if ($scheme === 'sendheron+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }
    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sendheron+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        return $scheme !== 'sendheron+api' || $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
    }
}

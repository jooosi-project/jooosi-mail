<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Primitive email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'primitive', label: 'Primitive', description: 'Send email through the Primitive API.', website: 'https://primitive.dev', docsUrl: 'https://docs.primitive.dev/docs/sending', useCases: ['transactional'])]
final class PrimitiveProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['primitive+api'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'primitive+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Primitive API key', 'type' => 'password', 'required' => \true]];
    }
    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'primitive+api';
        if ($scheme === 'primitive+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }
    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'primitive+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        return $scheme !== 'primitive+api' || $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
    }
}

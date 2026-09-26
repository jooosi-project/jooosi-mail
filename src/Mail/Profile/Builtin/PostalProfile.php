<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Postal transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'postal', label: 'Postal', description: 'Send mail through Postal using the Symfony bridge API transport.', website: 'https://docs.postalserver.io', docsUrl: 'https://docs.postalserver.io/developer/api', useCases: ['transactional', 'self-hosted'])]
final class PostalProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['postal+api'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'postal+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Postal API key', 'type' => 'password', 'required' => \true], 'host' => ['label' => 'Postal host', 'type' => 'text', 'required' => \true], 'port' => ['label' => 'Postal port', 'type' => 'number', 'required' => \false]];
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'postal+api';
        if ($scheme !== 'postal+api') {
            return null;
        }
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        $host = $this->extractScalarString($defaults, 'host');
        if ($apiKey === null || $apiKey === '' || $host === null || $host === '') {
            return null;
        }
        $dsn = 'postal+api://' . rawurlencode($apiKey) . '@' . $host;
        $port = $this->extractPositiveIntOrZero($defaults, 'port');
        if ($port !== null && $port > 0) {
            $dsn .= ':' . $port;
        }
        return $dsn;
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Cloudflare Email Service transport profile.
 *
 * @link https://developers.cloudflare.com/email-service/
 * @link https://developers.cloudflare.com/email-service/api/send-emails/rest-api/
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'cloudflare', label: 'Cloudflare Email Service', description: 'Send mail through Cloudflare Email Service using its REST API. Requires a Cloudflare-onboarded sending domain.', website: 'https://developers.cloudflare.com/email-service/', docsUrl: 'https://developers.cloudflare.com/email-service/api/send-emails/rest-api/', useCases: ['transactional'])]
final class CloudflareProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['cloudflare+api'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'cloudflare+api', 'choices' => $this->getSupportedSchemes()], 'account_id' => ['label' => 'Cloudflare account ID', 'type' => 'text', 'required' => \true], 'api_token' => ['label' => 'Cloudflare API token', 'type' => 'password', 'required' => \true]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'cloudflare+api';
        if ($scheme !== 'cloudflare+api') {
            return;
        }
        $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['account_id', 'api_token']);
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'cloudflare+api';
        if ($scheme !== 'cloudflare+api') {
            return null;
        }
        $accountId = $this->extractScalarString($defaults, 'account_id');
        $apiToken = is_string($defaults['api_token'] ?? null) ? (string) $defaults['api_token'] : null;
        if ($accountId === null || $accountId === '' || $apiToken === null || $apiToken === '') {
            return null;
        }
        return 'cloudflare+api://' . rawurlencode($accountId) . ':' . rawurlencode($apiToken) . '@default';
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionConfigurationException;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * toSend transport profile.
 *
 * @link https://tosend.com/docs/api/send-email/
 * @link https://tosend.com/docs/guide/webhooks/
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'tosend', label: 'toSend', description: 'Send mail through toSend using its REST API.', website: 'https://tosend.com', docsUrl: 'https://tosend.com/docs/api/send-email/', useCases: ['transactional'])]
final class ToSendProfile extends AbstractMailProfile
{
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['bounced', 'complaint'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'tosend+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'toSend API key', 'type' => 'password', 'required' => \true]];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['tosend+api'];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'tosend+api';
        if ($scheme === 'tosend+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
        if ($connection->webhookEnabled && !$connection->hasWebhookSecret()) {
            throw new ConnectionConfigurationException('Webhook secret is required for profile "tosend" when webhooks are enabled.');
        }
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'tosend+api';
        if ($scheme !== 'tosend+api') {
            return null;
        }
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        return $apiKey === null || $apiKey === '' ? null : 'tosend+api://' . rawurlencode($apiKey) . '@default';
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * Postmark transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(
    key: 'postmark',
    label: 'Postmark',
    description: 'Send mail through Postmark using the Symfony bridge API or SMTP transport.',
    website: 'https://postmarkapp.com',
    docsUrl: 'https://postmarkapp.com/developer',
    useCases: ['transactional'],
)]
final class PostmarkProfile extends AbstractMailProfile
{
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['delivered', 'bounce', 'spam_complaint', 'open', 'click', 'subscription_change'];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'postmark+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => ['label' => 'Postmark server token', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'postmark+api')], 'required_when' => [$this->conditionIn('scheme', 'postmark+api')]],
            'smtp_server_token' => ['label' => 'Postmark SMTP server token', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'postmark+smtp')], 'required_when' => [$this->conditionIn('scheme', 'postmark+smtp')]],
        ];
    }

    #[Override]
    public function supportsWebhooks(): bool
    {
        return true;
    }

    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['postmark+api', 'postmark+smtp'];
    }

    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'postmark+api';

        if (! in_array($scheme, $this->getSupportedSchemes(), true)) {
            return null;
        }

        $apiKey = $this->extractScalarString($defaults, $scheme === 'postmark+api' ? 'api_key' : 'smtp_server_token');

        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        return $scheme . '://' . rawurlencode($apiKey) . '@default';
    }
}

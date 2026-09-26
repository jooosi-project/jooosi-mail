<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * SendGrid transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(
    key: 'sendgrid',
    label: 'SendGrid',
    description: 'Send mail through SendGrid using the Symfony bridge API or SMTP transport.',
    website: 'https://sendgrid.com',
    docsUrl: 'https://www.twilio.com/docs/sendgrid',
    useCases: ['transactional', 'marketing'],
)]
final class SendGridProfile extends AbstractMailProfile
{
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['processed', 'deferred', 'delivered', 'bounce', 'dropped', 'open', 'click', 'unsubscribe', 'spam_report'];
    }

    #[Override]
    public function supportsWebhooks(): bool
    {
        return true;
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
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'sendgrid+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => ['label' => 'SendGrid API key', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'sendgrid+api')], 'required_when' => [$this->conditionIn('scheme', 'sendgrid+api')]],
            'smtp_api_key' => ['label' => 'SendGrid SMTP API key', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'sendgrid+smtp')], 'required_when' => [$this->conditionIn('scheme', 'sendgrid+smtp')]],
            'region' => ['label' => 'SendGrid region', 'type' => 'text', 'required' => false],
        ];
    }

    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sendgrid+api', 'sendgrid+smtp'];
    }

    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sendgrid+api';

        if (! in_array($scheme, $this->getSupportedSchemes(), true)) {
            return null;
        }

        $apiKey = $this->extractScalarString($defaults, $scheme === 'sendgrid+api' ? 'api_key' : 'smtp_api_key');

        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        $dsn = $scheme . '://' . rawurlencode($apiKey) . '@default';
        $query = $this->buildQueryString([
            'region' => $this->extractScalarString($defaults, 'region'),
        ]);

        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
}

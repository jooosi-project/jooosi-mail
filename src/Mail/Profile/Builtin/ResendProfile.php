<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Resend transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'resend', label: 'Resend', description: 'Send mail through Resend using the Symfony bridge API or SMTP transport.', website: 'https://resend.com', docsUrl: 'https://resend.com/docs', useCases: ['transactional'])]
final class ResendProfile extends AbstractMailProfile
{
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['email_sent', 'email_delivered', 'email_delivery_delayed', 'email_complained', 'email_bounced', 'email_opened', 'email_clicked'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'resend+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Resend API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'resend+api')], 'required_when' => [$this->conditionIn('scheme', 'resend+api')]], 'smtp_api_key' => ['label' => 'Resend SMTP API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'resend+smtp')], 'required_when' => [$this->conditionIn('scheme', 'resend+smtp')]]];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['resend+api', 'resend+smtp'];
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'resend+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        $apiKey = $this->extractScalarString($defaults, $scheme === 'resend+api' ? 'api_key' : 'smtp_api_key');
        if ($apiKey === null || $apiKey === '') {
            return null;
        }
        if ($scheme === 'resend+smtp') {
            return 'resend+smtp://' . rawurlencode('resend') . ':' . rawurlencode($apiKey) . '@default';
        }
        return 'resend+api://' . rawurlencode($apiKey) . '@default';
    }
}

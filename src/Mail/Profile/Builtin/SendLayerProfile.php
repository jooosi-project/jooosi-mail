<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * SendLayer transport profile.
 *
 * @link https://developers.sendlayer.com/api-reference/endpoint/email.md
 * @link https://sendlayer.com/docs/connecting-your-site-with-smtp/
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'sendlayer', label: 'SendLayer', description: 'Send mail through SendLayer using custom API or SMTP transports.', website: 'https://sendlayer.com', docsUrl: 'https://developers.sendlayer.com/api-reference/endpoint/email.md', useCases: ['transactional'])]
final class SendLayerProfile extends AbstractMailProfile
{
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['delivered', 'open', 'click', 'bounce', 'unsubscribed', 'spam_complaint'];
    }
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sendlayer+api', 'sendlayer+smtp', 'sendlayer+smtps'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'sendlayer+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'SendLayer API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'sendlayer+api')], 'required_when' => [$this->conditionIn('scheme', 'sendlayer+api')]], 'username' => ['label' => 'SendLayer SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['sendlayer+smtp', 'sendlayer+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['sendlayer+smtp', 'sendlayer+smtps'])]], 'password' => ['label' => 'SendLayer SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['sendlayer+smtp', 'sendlayer+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['sendlayer+smtp', 'sendlayer+smtps'])]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sendlayer+api';
        match ($scheme) {
            'sendlayer+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'sendlayer+smtp', 'sendlayer+smtps' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['username', 'password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sendlayer+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        if ($scheme === 'sendlayer+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            return $apiKey === null || $apiKey === '' ? null : $scheme . '://' . rawurlencode($apiKey) . '@default';
        }
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if ($username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        return $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

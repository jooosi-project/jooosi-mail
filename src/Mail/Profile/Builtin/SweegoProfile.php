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
 * Sweego transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'sweego', label: 'Sweego', description: 'Send mail through Sweego using the Symfony bridge API or SMTP transport.', website: 'https://www.sweego.io', docsUrl: 'https://learn.sweego.io/', useCases: ['transactional'])]
final class SweegoProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sweego+api', 'sweego+smtp'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['email_sent', 'delivered', 'soft_bounce', 'hard_bounce', 'list_unsub', 'complaint', 'email_clicked', 'email_opened'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'sweego+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Sweego API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'sweego+api')], 'required_when' => [$this->conditionIn('scheme', 'sweego+api')]], 'host' => ['label' => 'Sweego SMTP host', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'sweego+smtp')], 'required_when' => [$this->conditionIn('scheme', 'sweego+smtp')]], 'port' => ['label' => 'Sweego SMTP port', 'type' => 'number', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'sweego+smtp')], 'required_when' => [$this->conditionIn('scheme', 'sweego+smtp')]], 'username' => ['label' => 'Sweego SMTP login', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'sweego+smtp')], 'required_when' => [$this->conditionIn('scheme', 'sweego+smtp')]], 'password' => ['label' => 'Sweego SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'sweego+smtp')], 'required_when' => [$this->conditionIn('scheme', 'sweego+smtp')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sweego+api';
        match ($scheme) {
            'sweego+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'sweego+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['host', 'port', 'username', 'password']),
            default => null,
        };
        if ($connection->webhookEnabled && !$connection->hasWebhookSecret()) {
            throw new ConnectionConfigurationException('Webhook secret is required for profile "sweego" when webhooks are enabled.');
        }
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sweego+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        return match ($scheme) {
            'sweego+api' => $this->buildApiDsn($defaults),
            'sweego+smtp' => $this->buildSmtpDsn($defaults),
            default => null,
        };
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildApiDsn(array $defaults): ?string
    {
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        return $apiKey === null || $apiKey === '' ? null : 'sweego+api://' . rawurlencode($apiKey) . '@default';
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildSmtpDsn(array $defaults): ?string
    {
        $host = $this->extractScalarString($defaults, 'host');
        $port = $this->extractPositiveIntOrZero($defaults, 'port');
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if ($host === null || $host === '' || $port === null || $port <= 0 || $username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        return 'sweego+smtp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@' . $host . ':' . $port;
    }
}

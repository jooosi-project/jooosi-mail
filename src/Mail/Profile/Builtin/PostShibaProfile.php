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
 * PostShiba mail transport profile.
 *
 * @link https://www.postshiba.com/docs/send-with-http
 * @link https://www.postshiba.com/docs/send-with-smtp
 * @link https://www.postshiba.com/docs/webhooks/introduction
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'postshiba', label: 'PostShiba', description: 'Send mail through PostShiba using its REST API or SMTP relay.', website: 'https://www.postshiba.com', docsUrl: 'https://www.postshiba.com/docs/send-with-http', useCases: ['transactional'])]
final class PostShibaProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['postshiba+api', 'postshiba+smtp'];
    }
    /**
     * @since 1.0.13
     */
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    /**
     * @return list<string>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['processed', 'deferred', 'delivered', 'bounce', 'dropped', 'spam_report', 'unsubscribe'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        $apiScheme = 'postshiba+api';
        $smtpScheme = 'postshiba+smtp';
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => $apiScheme, 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'PostShiba send-only API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $apiScheme)], 'required_when' => [$this->conditionIn('scheme', $apiScheme)]], 'cluster_id' => ['label' => 'PostShiba cluster ID', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $apiScheme)]], 'smtp_username' => ['label' => 'PostShiba SMTP username', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $smtpScheme)], 'required_when' => [$this->conditionIn('scheme', $smtpScheme)]], 'smtp_password' => ['label' => 'PostShiba SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $smtpScheme)], 'required_when' => [$this->conditionIn('scheme', $smtpScheme)]]];
    }
    /**
     * @since 1.0.13
     */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'postshiba+api';
        match ($scheme) {
            'postshiba+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']),
            'postshiba+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_username', 'smtp_password']),
            default => null,
        };
        if ($connection->webhookEnabled && !$connection->hasWebhookSecret()) {
            throw new ConnectionConfigurationException('Webhook secret is required for profile "postshiba" when webhooks are enabled.');
        }
    }
    /**
     * @since 1.0.13
     */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'postshiba+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        if ($scheme === 'postshiba+api') {
            $apiKey = $this->extractScalarString($defaults, 'api_key');
            if ($apiKey === null || $apiKey === '') {
                return null;
            }
            $dsn = $scheme . '://' . rawurlencode($apiKey) . '@default';
            $query = $this->buildQueryString(['cluster_id' => $this->extractScalarString($defaults, 'cluster_id')]);
            return $query === '' ? $dsn : $dsn . '?' . $query;
        }
        $username = $this->extractScalarString($defaults, 'smtp_username');
        $password = $this->extractScalarString($defaults, 'smtp_password');
        return $username === null || $password === null ? null : $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
}

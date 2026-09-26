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
 * ZeptoMail transport profile.
 *
 * @link https://www.zoho.com/zeptomail/help/api/email-sending.html
 * @link https://www.zoho.com/zeptomail/help/smtp-home.html
 * @link https://www.zoho.com/zeptomail/help/webhooks.html
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'zeptomail', label: 'ZeptoMail', description: 'Send mail through ZeptoMail using the custom API or SMTP transport.', website: 'https://www.zoho.com/zeptomail/', docsUrl: 'https://www.zoho.com/zeptomail/help/api/email-sending.html', useCases: ['transactional'])]
final class ZeptoMailProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['zeptomail+api', 'zeptomail+smtp', 'zeptomail+smtps'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['soft_bounce', 'hard_bounce', 'open', 'click', 'delivered', 'feedback_loop'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'zeptomail+api', 'choices' => $this->getSupportedSchemes()], 'api_token' => ['label' => 'ZeptoMail API token', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'zeptomail+api')], 'required_when' => [$this->conditionIn('scheme', 'zeptomail+api')]], 'username' => ['label' => 'ZeptoMail SMTP username', 'type' => 'text', 'required' => \false, 'default' => 'emailapikey', 'visible_when' => [$this->conditionIn('scheme', ['zeptomail+smtp', 'zeptomail+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['zeptomail+smtp', 'zeptomail+smtps'])]], 'password' => ['label' => 'ZeptoMail SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['zeptomail+smtp', 'zeptomail+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['zeptomail+smtp', 'zeptomail+smtps'])]]];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationDefaults(?Connection $existingConnection = null): array
    {
        $defaults = parent::getConfigurationDefaults($existingConnection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'zeptomail+api';
        if (in_array($scheme, ['zeptomail+smtp', 'zeptomail+smtps'], \true) && !$this->hasConfigurationValue($defaults['password'] ?? null) && $this->hasConfigurationValue($defaults['api_token'] ?? null)) {
            $defaults['password'] = $defaults['api_token'];
        }
        return $defaults;
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'zeptomail+api';
        match ($scheme) {
            'zeptomail+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_token']),
            'zeptomail+smtp', 'zeptomail+smtps' => $this->validateSmtpConfiguration($defaults, $scheme),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'zeptomail+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        return match ($scheme) {
            'zeptomail+api' => $this->buildApiDsn($defaults),
            'zeptomail+smtp', 'zeptomail+smtps' => $this->buildSmtpDsn($defaults, $scheme),
            default => null,
        };
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function validateSmtpConfiguration(array $defaults, string $scheme): void
    {
        $username = $this->extractScalarString($defaults, 'username') ?? 'emailapikey';
        $password = $this->extractSmtpPassword($defaults);
        if ($username === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Configuration field "username" is required for profile "%s" when using scheme "%s".', $this->profileKey(), $scheme));
        }
        if ($password === null || $password === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Configuration field "password" is required for profile "%s" when using scheme "%s".', $this->profileKey(), $scheme));
        }
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildApiDsn(array $defaults): ?string
    {
        $apiToken = $this->extractScalarString($defaults, 'api_token');
        return $apiToken === null || $apiToken === '' ? null : 'zeptomail+api://' . rawurlencode($apiToken) . '@default';
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildSmtpDsn(array $defaults, string $scheme): ?string
    {
        $username = $this->extractScalarString($defaults, 'username') ?? 'emailapikey';
        $password = $this->extractSmtpPassword($defaults);
        if ($username === '' || $password === null || $password === '') {
            return null;
        }
        return $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function extractSmtpPassword(array $defaults): ?string
    {
        if (is_string($defaults['password'] ?? null) && $defaults['password'] !== '') {
            return (string) $defaults['password'];
        }
        return is_string($defaults['api_token'] ?? null) ? (string) $defaults['api_token'] : null;
    }
}

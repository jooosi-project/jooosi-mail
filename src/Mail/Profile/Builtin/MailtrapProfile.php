<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Mailtrap transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'mailtrap', label: 'Mailtrap', description: 'Send mail through Mailtrap using the Symfony bridge SMTP, live API, or sandbox API transport.', website: 'https://mailtrap.io', docsUrl: 'https://help.mailtrap.io/', useCases: ['testing', 'transactional'])]
final class MailtrapProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['mailtrap+smtp', 'mailtrap+api', 'mailtrap+sandbox'];
    }
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \true;
    }
    #[Override]
    public function getWebhookEvents(): array
    {
        return ['delivery', 'open', 'click', 'unsubscribe', 'spam', 'soft_bounce', 'bounce', 'suspension', 'reject'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'mailtrap+api', 'choices' => $this->getSupportedSchemes()], 'token' => ['label' => 'Mailtrap token', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', ['mailtrap+api', 'mailtrap+sandbox'])], 'required_when' => [$this->conditionIn('scheme', ['mailtrap+api', 'mailtrap+sandbox'])]], 'password' => ['label' => 'Mailtrap SMTP API token', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailtrap+smtp')], 'required_when' => [$this->conditionIn('scheme', 'mailtrap+smtp')]], 'inbox_id' => ['label' => 'Mailtrap sandbox inbox ID', 'type' => 'number', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'mailtrap+sandbox')], 'required_when' => [$this->conditionIn('scheme', 'mailtrap+sandbox')]]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailtrap+api';
        match ($scheme) {
            'mailtrap+api' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['token']),
            'mailtrap+sandbox' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['token', 'inbox_id']),
            'mailtrap+smtp' => $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['password']),
            default => null,
        };
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'mailtrap+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        return match ($scheme) {
            'mailtrap+api' => $this->buildApiDsn($defaults),
            'mailtrap+sandbox' => $this->buildSandboxDsn($defaults),
            'mailtrap+smtp' => $this->buildSmtpDsn($defaults),
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
        $token = is_string($defaults['token'] ?? null) ? trim((string) $defaults['token']) : null;
        return $token === null || $token === '' ? null : 'mailtrap+api://' . rawurlencode($token) . '@default';
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildSandboxDsn(array $defaults): ?string
    {
        $token = is_string($defaults['token'] ?? null) ? trim((string) $defaults['token']) : null;
        $inboxId = $this->extractPositiveIntOrZero($defaults, 'inbox_id');
        if ($token === null || $token === '' || $inboxId === null || $inboxId <= 0) {
            return null;
        }
        return 'mailtrap+sandbox://' . rawurlencode($token) . '@default?inboxId=' . $inboxId;
    }
    /**
     * @param array<string, mixed> $defaults
     *
     * @since 0.1.0
     */
    private function buildSmtpDsn(array $defaults): ?string
    {
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        return $password === null || $password === '' ? null : 'mailtrap+smtp://' . rawurlencode($password) . '@default';
    }
}

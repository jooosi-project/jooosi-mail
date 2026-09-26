<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Standard SMTP profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'smtp', label: 'SMTP', description: 'Send mail through a remote SMTP server.', docsUrl: 'https://symfony.com/doc/current/mailer.html', useCases: ['transactional', 'marketing', 'self-hosted'])]
final class SmtpProfile extends AbstractMailProfile
{
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['smtp', 'smtps'];
    }
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'SMTP scheme', 'type' => 'choice', 'required' => \false, 'default' => 'smtp', 'choices' => ['smtp', 'smtps']], 'host' => ['label' => 'SMTP host', 'type' => 'text', 'required' => \true], 'port' => ['label' => 'SMTP port', 'type' => 'number', 'required' => \false, 'default' => 587], 'username' => ['label' => 'SMTP username', 'type' => 'text', 'required' => \false], 'password' => ['label' => 'SMTP password', 'type' => 'password', 'required' => \false]];
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = strtolower((string) ($defaults['scheme'] ?? 'smtp'));
        if (!in_array($scheme, ['smtp', 'smtps'], \true)) {
            return null;
        }
        $host = $this->extractScalarString($defaults, 'host') ?? $defaults['host'] ?? null;
        if (!is_string($host) || $host === '') {
            return null;
        }
        $port = $this->extractPositiveIntOrZero($defaults, 'port') ?? (is_int($defaults['port'] ?? null) ? (int) $defaults['port'] : null);
        $username = $this->extractScalarString($defaults, 'username') ?? (is_string($defaults['username'] ?? null) ? (string) $defaults['username'] : null);
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        $authority = $host;
        if ($username !== null && $username !== '') {
            $authority = rawurlencode($username);
            if ($password !== null) {
                $authority .= ':' . rawurlencode($password);
            }
            $authority .= '@' . $host;
        }
        if ($port !== null && $port > 0) {
            $authority .= ':' . $port;
        }
        return $scheme . '://' . $authority;
    }
}

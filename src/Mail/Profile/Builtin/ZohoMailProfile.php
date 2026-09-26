<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Zoho Mail transport profile.
 *
 * @link https://www.zoho.com/mail/help/zoho-smtp.html
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'zohomail', label: 'Zoho Mail', description: 'Send mail through Zoho Mail using the custom SMTP transport.', website: 'https://www.zoho.com/mail/', docsUrl: 'https://www.zoho.com/mail/help/zoho-smtp.html', useCases: ['transactional'])]
final class ZohoMailProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['zohomail+smtp', 'zohomail+smtps'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'zohomail+smtp', 'choices' => $this->getSupportedSchemes()], 'username' => ['label' => 'Zoho Mail username', 'type' => 'text', 'required' => \true], 'password' => ['label' => 'Zoho Mail password', 'type' => 'password', 'required' => \true], 'account_type' => ['label' => 'Zoho account type', 'type' => 'choice', 'required' => \false, 'default' => 'personal', 'choices' => ['personal', 'business', 'pro']]];
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'zohomail+smtp';
        $username = $this->extractScalarString($defaults, 'username');
        $password = is_string($defaults['password'] ?? null) ? (string) $defaults['password'] : null;
        if (!in_array($scheme, $this->getSupportedSchemes(), \true) || $username === null || $username === '' || $password === null || $password === '') {
            return null;
        }
        $query = $this->buildQueryString(['account_type' => $this->normalizeAccountType($defaults['account_type'] ?? null)]);
        $dsn = $scheme . '://' . rawurlencode($username) . ':' . rawurlencode($password) . '@default';
        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
    /**
     * @since 0.1.0
     */
    private function normalizeAccountType(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $accountType = trim((string) $value);
        if ($accountType === '' || $accountType === 'personal') {
            return null;
        }
        return $accountType;
    }
}

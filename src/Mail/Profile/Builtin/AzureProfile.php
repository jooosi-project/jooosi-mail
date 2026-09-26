<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Azure Communication Services transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'azure', label: 'Azure', description: 'Send mail through Azure Communication Services using the Symfony bridge API transport.', website: 'https://azure.microsoft.com/en-us/products/communication-services', docsUrl: 'https://learn.microsoft.com/en-us/azure/communication-services/concepts/email/email-overview', useCases: ['transactional'])]
final class AzureProfile extends AbstractMailProfile
{
    private const DEFAULT_API_VERSION = '2023-03-31';
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['azure+api'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'azure+api', 'choices' => $this->getSupportedSchemes()], 'resource_name' => ['label' => 'Azure ACS resource name', 'type' => 'text', 'required' => \true], 'api_key' => ['label' => 'Azure ACS API key', 'type' => 'password', 'required' => \true], 'api_version' => ['label' => 'Azure API version', 'type' => 'text', 'required' => \false], 'disable_tracking' => ['label' => 'Disable Azure tracking', 'type' => 'choice', 'required' => \false, 'default' => 'false', 'choices' => ['false', 'true']]];
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'azure+api';
        if ($scheme !== 'azure+api') {
            return null;
        }
        $resourceName = $this->extractScalarString($defaults, 'resource_name');
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        if ($resourceName === null || $resourceName === '' || $apiKey === null || $apiKey === '') {
            return null;
        }
        $query = $this->buildQueryString(['api_version' => $this->normalizeApiVersion($defaults['api_version'] ?? null), 'disable_tracking' => $this->normalizeDisableTracking($defaults['disable_tracking'] ?? null)]);
        $dsn = 'azure+api://' . rawurlencode($resourceName) . ':' . rawurlencode($apiKey) . '@default';
        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
    /**
     * @since 0.1.0
     */
    private function normalizeApiVersion(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $apiVersion = trim((string) $value);
        if ($apiVersion === '' || $apiVersion === self::DEFAULT_API_VERSION) {
            return null;
        }
        return $apiVersion;
    }
    /**
     * @since 0.1.0
     */
    private function normalizeDisableTracking(mixed $value): ?string
    {
        $normalized = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        return $normalized === \true ? 'true' : null;
    }
}

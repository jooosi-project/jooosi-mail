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
 * Microsoft Graph transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'microsoftgraph', label: 'Microsoft Graph', description: 'Send mail through Microsoft Graph using the Symfony bridge API transport.', website: 'https://developer.microsoft.com/en-us/graph', docsUrl: 'https://learn.microsoft.com/en-us/graph/api/user-sendmail', useCases: ['transactional'])]
final class MicrosoftGraphProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['microsoftgraph+api'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'microsoftgraph+api', 'choices' => $this->getSupportedSchemes()], 'client_app_id' => ['label' => 'Microsoft Graph client app ID', 'type' => 'text', 'required' => \true], 'client_app_secret' => ['label' => 'Microsoft Graph client app secret', 'type' => 'password', 'required' => \true], 'tenant_id' => ['label' => 'Microsoft Graph tenant ID', 'type' => 'text', 'required' => \true], 'graph_endpoint' => ['label' => 'Microsoft Graph endpoint host', 'type' => 'text', 'required' => \false], 'auth_endpoint' => ['label' => 'Microsoft auth endpoint host', 'type' => 'text', 'required' => \false, 'required_message' => 'Configuration field "auth_endpoint" is required for profile "microsoftgraph" when using a custom graph endpoint.', 'visible_when' => [$this->conditionNotIn('graph_endpoint', ['', 'default', 'graph.microsoft.com'])], 'required_when' => [$this->conditionNotIn('graph_endpoint', ['', 'default', 'graph.microsoft.com'])]], 'no_save' => ['label' => 'Do not save to Sent Items', 'type' => 'choice', 'required' => \false, 'default' => 'false', 'choices' => ['false', 'true']]];
    }
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'microsoftgraph+api';
        if ($scheme !== 'microsoftgraph+api') {
            return;
        }
        $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['client_app_id', 'client_app_secret', 'tenant_id']);
        if ($this->normalizeGraphEndpoint($defaults['graph_endpoint'] ?? null) !== 'default' && $this->normalizeAuthEndpoint($defaults['auth_endpoint'] ?? null, 'custom') === null) {
            throw new ConnectionConfigurationException('Configuration field "auth_endpoint" is required for profile "microsoftgraph" when using a custom graph endpoint.');
        }
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'microsoftgraph+api';
        if ($scheme !== 'microsoftgraph+api') {
            return null;
        }
        $clientAppId = $this->extractScalarString($defaults, 'client_app_id');
        $clientAppSecret = is_string($defaults['client_app_secret'] ?? null) ? (string) $defaults['client_app_secret'] : null;
        $tenantId = $this->extractScalarString($defaults, 'tenant_id');
        if ($clientAppId === null || $clientAppId === '' || $clientAppSecret === null || $clientAppSecret === '' || $tenantId === null || $tenantId === '') {
            return null;
        }
        $graphEndpoint = $this->normalizeGraphEndpoint($defaults['graph_endpoint'] ?? null);
        $query = $this->buildQueryString(['tenantId' => $tenantId, 'authEndpoint' => $this->normalizeAuthEndpoint($defaults['auth_endpoint'] ?? null, $graphEndpoint), 'noSave' => $this->normalizeNoSave($defaults['no_save'] ?? null)]);
        $dsn = 'microsoftgraph+api://' . rawurlencode($clientAppId) . ':' . rawurlencode($clientAppSecret) . '@' . $graphEndpoint;
        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
    /**
     * @since 0.1.0
     */
    private function normalizeGraphEndpoint(mixed $value): string
    {
        if (!is_scalar($value)) {
            return 'default';
        }
        $graphEndpoint = trim((string) $value);
        if ($graphEndpoint === '' || strcasecmp($graphEndpoint, 'default') === 0 || strcasecmp($graphEndpoint, 'graph.microsoft.com') === 0) {
            return 'default';
        }
        return $graphEndpoint;
    }
    /**
     * @since 0.1.0
     */
    private function normalizeAuthEndpoint(mixed $value, string $graphEndpoint): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $authEndpoint = trim((string) $value);
        if ($authEndpoint === '') {
            return null;
        }
        if ($graphEndpoint === 'default' && strcasecmp($authEndpoint, 'login.microsoftonline.com') === 0) {
            return null;
        }
        return $authEndpoint;
    }
    /**
     * @since 0.1.0
     */
    private function normalizeNoSave(mixed $value): ?string
    {
        $normalized = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        return $normalized === \true ? 'true' : null;
    }
}

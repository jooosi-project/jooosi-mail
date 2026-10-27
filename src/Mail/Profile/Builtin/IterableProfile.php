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
 * Iterable transactional campaign profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'iterable', label: 'Iterable', description: 'Send through an Iterable email campaign and its configured template.', website: 'https://iterable.com', docsUrl: 'https://support.iterable.com/hc/en-us/articles/204780579-Iterable-API-Endpoints-and-Sample-Payloads', useCases: ['transactional', 'marketing'])]
final class IterableProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['iterable+api'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'iterable+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Iterable API key', 'type' => 'password', 'required' => \true], 'campaign_id' => ['label' => 'Transactional campaign ID', 'type' => 'number', 'required' => \true, 'min' => 1], 'region' => ['label' => 'API region', 'type' => 'choice', 'required' => \false, 'default' => 'us', 'choices' => ['us', 'eu']]];
    }
    /**
     * @since 1.0.13
     */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'iterable+api';
        if ($scheme === 'iterable+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key', 'campaign_id']);
            if ((int) ($defaults['campaign_id'] ?? 0) < 1) {
                throw new ConnectionConfigurationException('Iterable requires a positive transactional campaign ID.');
            }
        }
    }
    /**
     * @since 1.0.13
     */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'iterable+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        if ($scheme !== 'iterable+api' || $apiKey === null || $apiKey === '' || (int) ($defaults['campaign_id'] ?? 0) < 1) {
            return null;
        }
        $query = $this->buildQueryString(['campaign_id' => $this->extractPositiveIntOrZero($defaults, 'campaign_id'), 'region' => $this->extractScalarString($defaults, 'region') ?? 'us']);
        return 'iterable+api://' . rawurlencode($apiKey) . '@default' . ($query === '' ? '' : '?' . $query);
    }
}

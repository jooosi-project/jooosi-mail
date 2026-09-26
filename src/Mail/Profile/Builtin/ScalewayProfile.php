<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Scaleway transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'scaleway', label: 'Scaleway', description: 'Send mail through Scaleway using the Symfony bridge API or SMTP transport.', website: 'https://www.scaleway.com/en/transactional-email-tem/', docsUrl: 'https://developers.scaleway.com/en/products/transactional_email/api/', useCases: ['transactional'])]
final class ScalewayProfile extends AbstractMailProfile
{
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['scaleway+api', 'scaleway+smtp'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'scaleway+api', 'choices' => $this->getSupportedSchemes()], 'project_id' => ['label' => 'Scaleway API project ID', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'scaleway+api')], 'required_when' => [$this->conditionIn('scheme', 'scaleway+api')]], 'api_key' => ['label' => 'Scaleway API key', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'scaleway+api')], 'required_when' => [$this->conditionIn('scheme', 'scaleway+api')]], 'smtp_project_id' => ['label' => 'Scaleway SMTP username (project ID)', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'scaleway+smtp')], 'required_when' => [$this->conditionIn('scheme', 'scaleway+smtp')]], 'smtp_api_key' => ['label' => 'Scaleway SMTP password (API key secret)', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'scaleway+smtp')], 'required_when' => [$this->conditionIn('scheme', 'scaleway+smtp')]], 'region' => ['label' => 'Scaleway region', 'type' => 'text', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', 'scaleway+api')]]];
    }
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'scaleway+api';
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            return null;
        }
        $usesApi = $scheme === 'scaleway+api';
        $projectId = $this->extractScalarString($defaults, $usesApi ? 'project_id' : 'smtp_project_id');
        $apiKey = $this->extractScalarString($defaults, $usesApi ? 'api_key' : 'smtp_api_key');
        if ($projectId === null || $projectId === '' || $apiKey === null || $apiKey === '') {
            return null;
        }
        $dsn = $scheme . '://' . rawurlencode($projectId) . ':' . rawurlencode($apiKey) . '@default';
        if ($scheme !== 'scaleway+api') {
            return $dsn;
        }
        $query = $this->buildQueryString(['region' => $this->extractScalarString($defaults, 'region')]);
        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
}

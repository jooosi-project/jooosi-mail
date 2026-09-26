<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * SparkPost transport profile.
 *
 * @link https://developers.sparkpost.com/api/transmissions/
 * @link https://developers.sparkpost.com/api/smtp/
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(
    key: 'sparkpost',
    label: 'SparkPost',
    description: 'Send mail through SparkPost using custom API or SMTP transports.',
    website: 'https://www.sparkpost.com',
    docsUrl: 'https://developers.sparkpost.com/api/transmissions/',
    useCases: ['transactional', 'marketing'],
)]
final class SparkPostProfile extends AbstractMailProfile
{
    #[Override]
    public function supportsWebhooks(): bool
    {
        return true;
    }

    #[Override]
    public function getWebhookEvents(): array
    {
        return ['injection', 'delivery', 'delay', 'bounce', 'spam_complaint', 'out_of_band', 'policy_rejection', 'generation_failure', 'generation_rejection', 'open', 'initial_open', 'click', 'initial_click', 'amp_click', 'amp_open'];
    }

    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sparkpost+api', 'sparkpost+smtp', 'sparkpost+smtps'];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'sparkpost+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => ['label' => 'SparkPost API key', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'sparkpost+api')], 'required_when' => [$this->conditionIn('scheme', 'sparkpost+api')]],
            'smtp_api_key' => ['label' => 'SparkPost SMTP API key', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', ['sparkpost+smtp', 'sparkpost+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['sparkpost+smtp', 'sparkpost+smtps'])]],
            'region' => ['label' => 'SparkPost region', 'type' => 'choice', 'required' => false, 'choices' => ['eu']],
        ];
    }

    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sparkpost+api';
        $apiKey = $this->extractScalarString($defaults, $scheme === 'sparkpost+api' ? 'api_key' : 'smtp_api_key');

        if (! in_array($scheme, $this->getSupportedSchemes(), true) || $apiKey === null || $apiKey === '') {
            return null;
        }

        $query = $this->buildQueryString(['region' => $this->extractScalarString($defaults, 'region')]);
        $dsn = $scheme . '://' . rawurlencode($apiKey) . '@default';

        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * Loops transactional email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'loops', label: 'Loops', description: 'Send through a published Loops transactional email template using its API or SMTP relay.', website: 'https://loops.so', docsUrl: 'https://app.loops.so/docs/transactional/guide', useCases: ['transactional'])]
final class LoopsProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['loops+api', 'loops+smtp'];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'loops+api', 'choices' => $this->getSupportedSchemes()],
            'api_key' => [
                'label' => 'Loops API key / SMTP password',
                'type' => 'password',
                'required' => true,
                'visible_when' => [$this->conditionIn('scheme', $this->getSupportedSchemes())],
                'required_when' => [$this->conditionIn('scheme', $this->getSupportedSchemes())],
            ],
            'transactional_id' => ['label' => 'Transactional email ID', 'type' => 'text', 'required' => true],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'loops+api';
        if (in_array($scheme, $this->getSupportedSchemes(), true)) {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key', 'transactional_id']);
        }
    }

    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'loops+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        $transactionalId = $this->extractScalarString($defaults, 'transactional_id');
        if (! in_array($scheme, $this->getSupportedSchemes(), true) || $apiKey === null || $apiKey === '' || $transactionalId === null || $transactionalId === '') {
            return null;
        }

        $query = $this->buildQueryString(['transactional_id' => $transactionalId]);

        $username = $scheme === 'loops+api' ? rawurlencode($apiKey) : 'loops';
        $password = $scheme === 'loops+api' ? '' : ':' . rawurlencode($apiKey);

        return $scheme . '://' . $username . $password . '@default' . ($query === '' ? '' : '?' . $query);
    }
}

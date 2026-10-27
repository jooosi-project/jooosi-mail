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
#[MailProfile(key: 'loops', label: 'Loops', description: 'Send through a published Loops transactional email template.', website: 'https://loops.so', docsUrl: 'https://app.loops.so/docs/transactional/guide', useCases: ['transactional'])]
final class LoopsProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['loops+api'];
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
            'api_key' => ['label' => 'Loops API key', 'type' => 'password', 'required' => true],
            'transactional_id' => ['label' => 'Transactional email ID', 'type' => 'text', 'required' => true],
        ];
    }

    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'loops+api';
        if ($scheme === 'loops+api') {
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
        if ($scheme !== 'loops+api' || $apiKey === null || $apiKey === '' || $transactionalId === null || $transactionalId === '') {
            return null;
        }

        $query = $this->buildQueryString(['transactional_id' => $transactionalId]);

        return 'loops+api://' . rawurlencode($apiKey) . '@default' . ($query === '' ? '' : '?' . $query);
    }
}

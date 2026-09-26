<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionConfigurationException;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;

/**
 * Bird transport profile.
 *
 * @link https://docs.bird.com/api/email-api/transmissions
 * @link https://docs.bird.com/api/email-api/smtp-api
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(
    key: 'bird',
    label: 'Bird',
    description: 'Send mail through Bird using custom API or SMTP transports.',
    website: 'https://bird.com',
    docsUrl: 'https://docs.bird.com/api/email-api/transmissions',
    useCases: ['transactional', 'marketing'],
)]
final class BirdProfile extends AbstractMailProfile
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

    /**
     * @return list<string>
     */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['bird+api', 'bird+smtp', 'bird+smtps'];
    }

    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'bird+api';

        if ($scheme === 'bird+api') {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['access_key', 'workspace_id']);

            return;
        }

        if (! in_array($scheme, ['bird+smtp', 'bird+smtps'], true)) {
            return;
        }

        $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['smtp_access_key']);

        $accessKey = $this->extractScalarString($defaults, 'smtp_access_key');

        if ($accessKey !== null && (str_starts_with($accessKey, 'bk_eu1_') || str_starts_with($accessKey, 'bk_us1_'))) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new ConnectionConfigurationException(sprintf('Configuration field "smtp_access_key" must use a Bird key with the "bk_eu1_" or "bk_us1_" prefix for profile "%s" when using scheme "%s".', $this->profileKey(), $scheme));
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return [
            'scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => false, 'default' => 'bird+api', 'choices' => $this->getSupportedSchemes()],
            'access_key' => ['label' => 'Bird API access key', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'bird+api')], 'required_when' => [$this->conditionIn('scheme', 'bird+api')]],
            'workspace_id' => ['label' => 'Bird API workspace ID', 'type' => 'text', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', 'bird+api')], 'required_when' => [$this->conditionIn('scheme', 'bird+api')]],
            'smtp_access_key' => ['label' => 'Bird SMTP API key', 'type' => 'password', 'required' => false, 'visible_when' => [$this->conditionIn('scheme', ['bird+smtp', 'bird+smtps'])], 'required_when' => [$this->conditionIn('scheme', ['bird+smtp', 'bird+smtps'])]],
            'region' => ['label' => 'Bird API region', 'type' => 'choice', 'required' => false, 'default' => 'eu', 'choices' => ['eu', 'us'], 'visible_when' => [$this->conditionIn('scheme', 'bird+api')]],
        ];
    }

    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'bird+api';
        $usesApi = $scheme === 'bird+api';
        $accessKey = $this->extractScalarString($defaults, $usesApi ? 'access_key' : 'smtp_access_key');

        if (! in_array($scheme, $this->getSupportedSchemes(), true) || $accessKey === null || $accessKey === '') {
            return null;
        }

        if (! $usesApi) {
            return $scheme . '://' . rawurlencode($accessKey) . '@default';
        }

        $workspaceId = $this->extractScalarString($defaults, 'workspace_id');

        if ($workspaceId === null || $workspaceId === '') {
            return null;
        }

        $query = $this->buildQueryString([
            'workspace_id' => $workspaceId,
            'region' => $this->extractScalarString($defaults, 'region'),
        ]);
        $dsn = $scheme . '://' . rawurlencode($accessKey) . '@default';

        return $query === '' ? $dsn : $dsn . '?' . $query;
    }
}

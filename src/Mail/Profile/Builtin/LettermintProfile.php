<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Lettermint email profile.
 *
 * @link https://lettermint.co/docs/guides/send-email-with-smtp
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'lettermint', label: 'Lettermint', description: 'Send email through Lettermint using its Sending API or SMTP relay.', website: 'https://lettermint.co', docsUrl: 'https://lettermint.co/docs/api-reference/sending/send', useCases: ['transactional'])]
final class LettermintProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['lettermint+api', 'lettermint+smtp', 'lettermint+smtps'];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.13
     */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'lettermint+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Lettermint Project API token', 'type' => 'password', 'required' => \true], 'route' => ['label' => 'Route (optional)', 'type' => 'text', 'required' => \false]];
    }
    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'lettermint+api';
        if (in_array($scheme, $this->getSupportedSchemes(), \true)) {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }
    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'lettermint+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        if (!in_array($scheme, $this->getSupportedSchemes(), \true) || $apiKey === null || $apiKey === '') {
            return null;
        }
        $query = $this->buildQueryString(['route' => $this->extractScalarString($defaults, 'route')]);
        $routeQuery = $query === '' ? '' : '?' . $query;
        if ($scheme === 'lettermint+api') {
            return $scheme . '://' . rawurlencode($apiKey) . '@default' . $routeQuery;
        }
        return $scheme . '://lettermint:' . rawurlencode($apiKey) . '@default' . $routeQuery;
    }
}

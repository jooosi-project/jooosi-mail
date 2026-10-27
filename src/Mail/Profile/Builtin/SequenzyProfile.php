<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Sequenzy transactional email profile.
 *
 * @since 1.0.13
 */
#[Service]
#[MailProfile(key: 'sequenzy', label: 'Sequenzy', description: 'Send transactional mail through the Sequenzy API or SMTP relay.', website: 'https://www.sequenzy.com', docsUrl: 'https://docs.sequenzy.com/api-reference/transactional/send', useCases: ['transactional'])]
final class SequenzyProfile extends AbstractMailProfile
{
    /** @return list<string> */
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sequenzy+api', 'sequenzy+smtp', 'sequenzy+smtps'];
    }
    /** @return array<string, mixed> */
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['scheme' => ['label' => 'Transport scheme', 'type' => 'choice', 'required' => \false, 'default' => 'sequenzy+api', 'choices' => $this->getSupportedSchemes()], 'api_key' => ['label' => 'Sequenzy API key / SMTP password', 'type' => 'password', 'required' => \false, 'visible_when' => [$this->conditionIn('scheme', $this->getSupportedSchemes())], 'required_when' => [$this->conditionIn('scheme', $this->getSupportedSchemes())]]];
    }
    /** @since 1.0.13 */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sequenzy+api';
        if (in_array($scheme, $this->getSupportedSchemes(), \true)) {
            $this->assertRequiredConfigurationValues($defaults, $this->profileKey(), $scheme, ['api_key']);
        }
    }
    /** @since 1.0.13 */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $scheme = $this->extractScalarString($defaults, 'scheme') ?? 'sequenzy+api';
        $apiKey = $this->extractScalarString($defaults, 'api_key');
        if (!in_array($scheme, $this->getSupportedSchemes(), \true) || $apiKey === null || $apiKey === '') {
            return null;
        }
        return $scheme === 'sequenzy+api' ? $scheme . '://' . rawurlencode($apiKey) . '@default' : $scheme . '://api:' . rawurlencode($apiKey) . '@default';
    }
}

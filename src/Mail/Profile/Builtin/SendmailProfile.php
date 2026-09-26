<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Local sendmail profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'sendmail', label: 'Sendmail', description: 'Send mail through a local sendmail binary.', docsUrl: 'https://symfony.com/doc/current/mailer.html', useCases: ['transactional'])]
final class SendmailProfile extends AbstractMailProfile
{
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['sendmail'];
    }
    #[Override]
    public function getConfigurationFields(): array
    {
        return ['command' => ['label' => 'Sendmail command', 'type' => 'text', 'required' => \false]];
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        $defaults = $this->getConfigurationDefaults($connection);
        $command = $this->extractScalarString($defaults, 'command') ?? (is_string($defaults['command'] ?? null) ? (string) $defaults['command'] : null);
        if ($command === null || $command === '') {
            return 'sendmail://default';
        }
        return 'sendmail://default?command=' . rawurlencode($command);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * Null transport profile for testing.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'null', label: 'Null', description: 'Discard messages without delivering them.', docsUrl: 'https://symfony.com/doc/current/mailer.html', useCases: ['testing'])]
final class NullProfile extends AbstractMailProfile
{
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['null'];
    }
    #[Override]
    public function getConfigurationFields(): array
    {
        return [];
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function buildDsn(Connection $connection): ?string
    {
        return 'null://null';
    }
}

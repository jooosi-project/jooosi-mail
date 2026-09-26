<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile\Builtin;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Profile\AbstractMailProfile;
use Override;
/**
 * PHP native transport profile.
 *
 * @since 0.1.0
 */
#[Service]
#[MailProfile(key: 'native', label: 'Native PHP', description: 'Use the PHP native mail configuration.', docsUrl: 'https://symfony.com/doc/current/mailer.html', useCases: ['transactional'])]
final class NativeProfile extends AbstractMailProfile
{
    #[Override]
    public function getSupportedSchemes(): array
    {
        return ['native'];
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
        return 'native://default';
    }
}

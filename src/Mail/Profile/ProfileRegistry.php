<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use JooosiMailDeps\Psr\Container\ContainerInterface;
/**
 * Runtime registry for discovered mail profiles.
 *
 * @since 0.1.0
 */
#[Service]
final class ProfileRegistry
{
    public function __construct(private readonly DiscoveryManifest $manifest, private readonly ContainerInterface $container, private readonly \JooosiMail\Mail\Profile\ProfileMetadataResolver $profileMetadataResolver)
    {
    }
    /**
     * @return list<MailProfileInterface>
     *
     * @since 0.1.0
     */
    public function all(): array
    {
        $profiles = [];
        foreach ($this->manifest->profiles as $className) {
            $profile = $this->container->get($className);
            if ($profile instanceof \JooosiMail\Mail\Profile\MailProfileInterface) {
                $profiles[] = $profile;
            }
        }
        return $profiles;
    }
    /**
     * @since 0.1.0
     */
    public function get(string $key): ?\JooosiMail\Mail\Profile\MailProfileInterface
    {
        foreach ($this->all() as $profile) {
            if ($this->profileMetadataResolver->getKey($profile) === $key) {
                return $profile;
            }
        }
        return null;
    }
}

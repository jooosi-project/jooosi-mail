<?php

declare (strict_types=1);
namespace JooosiMailDeps\AsyncAws\Core\Credentials;

use JooosiMailDeps\AsyncAws\Core\Configuration;
/**
 * Interface for providing Credential.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface CredentialProvider
{
    /**
     * Return a Credential when possible. Return null otherwise.
     */
    public function getCredentials(Configuration $configuration): ?Credentials;
}

<?php

namespace JooosiMailDeps\AsyncAws\Core\Signer;

use JooosiMailDeps\AsyncAws\Core\Credentials\Credentials;
use JooosiMailDeps\AsyncAws\Core\Request;
use JooosiMailDeps\AsyncAws\Core\RequestContext;
/**
 * Interface for signing a request.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface Signer
{
    public function sign(Request $request, Credentials $credentials, RequestContext $context): void;
    public function presign(Request $request, Credentials $credentials, RequestContext $context): void;
}

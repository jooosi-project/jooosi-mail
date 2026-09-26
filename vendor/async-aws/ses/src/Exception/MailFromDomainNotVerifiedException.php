<?php

namespace JooosiMailDeps\AsyncAws\Ses\Exception;

use JooosiMailDeps\AsyncAws\Core\Exception\Http\ClientException;
/**
 * The message can't be sent because the sending domain isn't verified.
 */
final class MailFromDomainNotVerifiedException extends ClientException
{
}

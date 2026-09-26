<?php

namespace JooosiMailDeps\AsyncAws\Ses\Exception;

use JooosiMailDeps\AsyncAws\Core\Exception\Http\ClientException;
/**
 * The message can't be sent because the account's ability to send email is currently paused.
 */
final class SendingPausedException extends ClientException
{
}

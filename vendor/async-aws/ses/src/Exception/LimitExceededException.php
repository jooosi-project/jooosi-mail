<?php

namespace JooosiMailDeps\AsyncAws\Ses\Exception;

use JooosiMailDeps\AsyncAws\Core\Exception\Http\ClientException;
/**
 * There are too many instances of the specified resource type.
 */
final class LimitExceededException extends ClientException
{
}

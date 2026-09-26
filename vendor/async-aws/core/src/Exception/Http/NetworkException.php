<?php

declare (strict_types=1);
namespace JooosiMailDeps\AsyncAws\Core\Exception\Http;

use JooosiMailDeps\AsyncAws\Core\Exception\Exception;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
/**
 * Request could not be sent due network error.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class NetworkException extends \RuntimeException implements Exception, TransportExceptionInterface
{
}

<?php

declare (strict_types=1);
namespace JooosiMailDeps\AsyncAws\Core\Exception\Http;

use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
/**
 * Represents a 5xx response.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ServerException extends \RuntimeException implements HttpException, ServerExceptionInterface
{
    use HttpExceptionTrait;
}

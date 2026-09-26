<?php

declare (strict_types=1);
namespace JooosiMailDeps\AsyncAws\Core\Exception\Http;

use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
/**
 * Represents a 3xx response.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class RedirectionException extends \RuntimeException implements HttpException, RedirectionExceptionInterface
{
    use HttpExceptionTrait;
}

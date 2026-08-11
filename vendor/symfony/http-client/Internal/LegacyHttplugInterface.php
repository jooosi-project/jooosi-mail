<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\HttpClient\Internal;

use JooosiMailDeps\Http\Client\HttpClient;
use JooosiMailDeps\Http\Message\RequestFactory;
use JooosiMailDeps\Http\Message\StreamFactory;
use JooosiMailDeps\Http\Message\UriFactory;
if (interface_exists(RequestFactory::class)) {
    /**
     * @internal
     *
     * @deprecated since Symfony 6.3
     */
    interface LegacyHttplugInterface extends HttpClient, RequestFactory, StreamFactory, UriFactory
    {
    }
} else {
    /**
     * @internal
     *
     * @deprecated since Symfony 6.3
     */
    interface LegacyHttplugInterface extends HttpClient
    {
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\HttpClient\Exception;

use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TransportException extends \RuntimeException implements TransportExceptionInterface
{
}

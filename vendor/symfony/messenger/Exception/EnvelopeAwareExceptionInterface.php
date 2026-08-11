<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Messenger\Exception;

use JooosiMailDeps\Symfony\Component\Messenger\Envelope;
/**
 * @internal
 */
interface EnvelopeAwareExceptionInterface
{
    public function getEnvelope(): ?Envelope;
}

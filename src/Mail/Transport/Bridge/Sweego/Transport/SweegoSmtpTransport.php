<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * Adapted from Symfony Mailer bridge source licensed under the MIT License.
 * See documentation/THIRD_PARTY_NOTICES.md for the original license notice.
 */

namespace JooosiMail\Mail\Transport\Bridge\Sweego\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class SweegoSmtpTransport extends EsmtpTransport
{
    public function __construct(string $host, int $port, string $login, #[\SensitiveParameter] string $password, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($host, $port, true, $dispatcher, $logger);

        $this->setUsername($login);
        $this->setPassword($password);
    }
}

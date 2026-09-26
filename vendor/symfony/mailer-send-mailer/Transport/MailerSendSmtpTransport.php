<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Mailer\Bridge\MailerSend\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * @author Yann LUCAS
 */
final class MailerSendSmtpTransport extends EsmtpTransport
{
    public function __construct(
        string $username,
        #[\SensitiveParameter]
        string $password,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct('smtp.mailersend.net', 587, \false, $dispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

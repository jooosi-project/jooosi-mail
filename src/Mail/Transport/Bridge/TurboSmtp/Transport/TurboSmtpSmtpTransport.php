<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\TurboSmtp\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * TurboSMTP SMTP transport.
 *
 * @since 0.1.0
 */
final class TurboSmtpSmtpTransport extends EsmtpTransport
{
    public function __construct(
        string $host,
        string $username,
        #[SensitiveParameter]
        string $password,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct($host, 587, \false, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

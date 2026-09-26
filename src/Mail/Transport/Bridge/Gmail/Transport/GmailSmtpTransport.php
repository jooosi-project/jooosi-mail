<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Gmail\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * Gmail SMTP transport.
 *
 * @since 0.1.0
 */
final class GmailSmtpTransport extends EsmtpTransport
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        string $username,
        #[SensitiveParameter]
        string $password,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct('smtp.gmail.com', 465, \true, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

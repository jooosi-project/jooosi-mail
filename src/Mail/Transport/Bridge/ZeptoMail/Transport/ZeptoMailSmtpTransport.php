<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\ZeptoMail\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * ZeptoMail SMTP transport.
 *
 * @link https://www.zoho.com/zeptomail/help/smtp-home.html
 *
 * @since 0.1.0
 */
final class ZeptoMailSmtpTransport extends EsmtpTransport
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        string $username,
        #[SensitiveParameter]
        string $password,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        $port ??= 587;
        parent::__construct('smtp.zeptomail.com', $port, $port === 465, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

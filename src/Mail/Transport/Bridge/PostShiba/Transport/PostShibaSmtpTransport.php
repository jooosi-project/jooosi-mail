<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\PostShiba\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\Auth\PlainAuthenticator;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * PostShiba SMTP transport using STARTTLS and AUTH PLAIN.
 *
 * @link https://www.postshiba.com/docs/send-with-smtp
 *
 * @since 1.0.13
 */
final class PostShibaSmtpTransport extends EsmtpTransport
{
    /**
     * @since 1.0.13
     */
    public function __construct(
        string $username,
        #[SensitiveParameter]
        string $password,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct('smtp.postshiba.com', 587, \false, $eventDispatcher, $logger, null, [new PlainAuthenticator()]);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

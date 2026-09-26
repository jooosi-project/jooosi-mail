<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\MailKite\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * MailKite SMTP transport.
 *
 * @since 0.1.0
 */
final class MailKiteSmtpTransport extends EsmtpTransport
{
    public function __construct(
        string $username,
        #[SensitiveParameter]
        string $password,
        bool $implicitTls = \false,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        $port ??= $implicitTls ? 465 : 587;
        parent::__construct('smtp.mailkite.dev', $port, $implicitTls, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

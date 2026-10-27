<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Helo\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * Helo SMTP transport.
 *
 * @since 1.0.13
 */
final class HeloSmtpTransport extends EsmtpTransport
{
    /** @since 1.0.13 */
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
        parent::__construct('smtp.helohq.com', $port, $implicitTls, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

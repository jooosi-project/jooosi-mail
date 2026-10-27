<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Plunk\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * Plunk SMTP transport.
 *
 * @since 1.0.13
 */
final class PlunkSmtpTransport extends EsmtpTransport
{
    /** @since 1.0.13 */
    public function __construct(
        #[SensitiveParameter]
        string $password,
        bool $implicitTls = \false,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        $port ??= $implicitTls ? 465 : 587;
        parent::__construct('smtp.useplunk.com', $port, $implicitTls, $eventDispatcher, $logger);
        $this->setUsername('plunk');
        $this->setPassword($password);
    }
}

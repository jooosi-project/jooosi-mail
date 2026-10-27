<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\JetEmail\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * JetEmail SMTP transport.
 *
 * @since 1.0.13
 */
final class JetEmailSmtpTransport extends EsmtpTransport
{
    /** @since 1.0.13 */
    public function __construct(
        string $username,
        #[SensitiveParameter]
        string $password,
        string $region = 'global',
        bool $implicitTls = \false,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        $port ??= $implicitTls ? 465 : 587;
        $host = $region === 'eu' ? 'relay-eu.jetsmtp.net' : 'relay.jetsmtp.net';
        parent::__construct($host, $port, $implicitTls, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Pepipost\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * Pepipost SMTP transport.
 *
 * @since 0.1.0
 */
final class PepipostSmtpTransport extends EsmtpTransport
{
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
        parent::__construct('smtp.netcorecloud.net', $port, $port === 465, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\ElasticEmail\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * Elastic Email SMTP transport.
 *
 * @since 0.1.0
 */
final class ElasticEmailSmtpTransport extends EsmtpTransport
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
        parent::__construct('smtp.elasticemail.com', $port, $port === 465, $eventDispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
}

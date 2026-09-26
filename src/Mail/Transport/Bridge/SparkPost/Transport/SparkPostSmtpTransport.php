<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\SparkPost\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
final class SparkPostSmtpTransport extends EsmtpTransport
{
    public function __construct(
        #[SensitiveParameter]
        string $apiKey,
        ?string $region = null,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        $host = $region === 'eu' ? 'smtp.eu.sparkpostmail.com' : 'smtp.sparkpostmail.com';
        $port ??= 587;
        parent::__construct($host, $port, \false, $eventDispatcher, $logger);
        $this->setUsername('SMTP_Injection');
        $this->setPassword($apiKey);
    }
}

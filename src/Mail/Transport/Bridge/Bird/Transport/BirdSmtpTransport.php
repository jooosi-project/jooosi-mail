<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Bird\Transport;

use InvalidArgumentException;
use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
/**
 * Bird SMTP transport.
 *
 * @link https://docs.bird.com/api/email-api/smtp-api
 *
 * @since 0.1.0
 */
final class BirdSmtpTransport extends EsmtpTransport
{
    public function __construct(
        #[SensitiveParameter]
        string $accessKey,
        bool $implicitTls = \false,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        $host = match (\true) {
            str_starts_with($accessKey, 'bk_eu1_') => 'eu1.smtp.bird.com',
            str_starts_with($accessKey, 'bk_us1_') => 'us1.smtp.bird.com',
            default => throw new InvalidArgumentException('Bird SMTP access keys must have a supported region prefix (bk_eu1_ or bk_us1_).'),
        };
        $port ??= $implicitTls ? 465 : 587;
        parent::__construct($host, $port, $implicitTls, $eventDispatcher, $logger);
        $this->setUsername('bird');
        $this->setPassword($accessKey);
    }
}

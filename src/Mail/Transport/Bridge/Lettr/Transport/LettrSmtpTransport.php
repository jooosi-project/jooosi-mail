<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Lettr\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Lettr SMTP transport.
 *
 * @since 1.0.13
 */
final class LettrSmtpTransport extends EsmtpTransport
{
    /** @since 1.0.13 */
    public function __construct(
        #[SensitiveParameter] string $password,
        bool $implicitTls = false,
        ?int $port = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $port ??= $implicitTls ? 465 : 587;

        parent::__construct('smtp.lettr.com', $port, $implicitTls, $eventDispatcher, $logger);
        $this->setUsername('lettr');
        $this->setPassword($password);
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Lettermint\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * Lettermint SMTP transport.
 *
 * @since 1.0.13
 */
final class LettermintSmtpTransport extends EsmtpTransport
{
    public function __construct(
        #[SensitiveParameter] string $password,
        private readonly ?string $route = null,
        bool $implicitTls = false,
        ?int $port = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $port ??= $implicitTls ? 465 : 587;

        parent::__construct('smtp.lettermint.co', $port, $implicitTls, $dispatcher, $logger);
        $this->setUsername('lettermint');
        $this->setPassword($password);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($this->route !== null && $this->route !== '' && $message instanceof Message) {
            $message = clone $message;
            $headers = $message->getHeaders();

            if (! $headers->has('X-Lettermint-Route')) {
                $headers->addTextHeader('X-Lettermint-Route', $this->route);
            }
        }

        return parent::send($message, $envelope);
    }
}

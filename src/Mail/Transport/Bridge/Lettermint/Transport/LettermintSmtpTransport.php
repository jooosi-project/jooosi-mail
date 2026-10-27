<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Lettermint\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use JooosiMailDeps\Symfony\Component\Mime\Message;
use JooosiMailDeps\Symfony\Component\Mime\RawMessage;
/**
 * Lettermint SMTP transport.
 *
 * @since 1.0.13
 */
final class LettermintSmtpTransport extends EsmtpTransport
{
    public function __construct(
        #[SensitiveParameter]
        string $password,
        private readonly ?string $route = null,
        bool $implicitTls = \false,
        ?int $port = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
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
            if (!$headers->has('X-Lettermint-Route')) {
                $headers->addTextHeader('X-Lettermint-Route', $this->route);
            }
        }
        return parent::send($message, $envelope);
    }
}

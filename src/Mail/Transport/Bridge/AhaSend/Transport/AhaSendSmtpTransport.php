<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * Adapted from Symfony Mailer bridge source licensed under the MIT License.
 * See documentation/THIRD_PARTY_NOTICES.md for the original license notice.
 */
namespace JooosiMail\Mail\Transport\Bridge\AhaSend\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Header\TagHeader;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use JooosiMailDeps\Symfony\Component\Mime\Message;
use JooosiMailDeps\Symfony\Component\Mime\RawMessage;
/**
 * @author Farhad Hedayatifard <farhad@ahasend.com>
 */
class AhaSendSmtpTransport extends EsmtpTransport
{
    public function __construct(
        #[\SensitiveParameter]
        string $username,
        #[\SensitiveParameter]
        string $password,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct('send.ahasend.com', 587, \false, $dispatcher, $logger);
        $this->setUsername($username);
        $this->setPassword($password);
    }
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $this->addAhaSendHeaders($message);
        }
        return parent::send($message, $envelope);
    }
    private function addAhaSendHeaders(Message $message): void
    {
        $headers = $message->getHeaders();
        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
                $headers->remove($name);
            }
        }
        if (!empty($tags)) {
            $headers->addTextHeader('AhaSend-Tags', implode(',', $tags));
        }
    }
}

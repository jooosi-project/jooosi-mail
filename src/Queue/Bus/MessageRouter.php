<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Bus;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Message\SendEmailMessage;
use JooosiMail\Queue\Transport\DatabaseTransport;
use Override;
use JooosiMailDeps\Symfony\Component\Messenger\Envelope;
use JooosiMailDeps\Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
/**
 * Routes async messages to the Jooosi Mail database transport.
 *
 * @since 0.1.0
 */
#[Service]
final class MessageRouter implements SendersLocatorInterface
{
    public function __construct(private readonly DatabaseTransport $databaseTransport)
    {
    }
    /**
     * @return iterable<string, SenderInterface>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getSenders(Envelope $envelope): iterable
    {
        $message = $envelope->getMessage();
        $transportNamesStamp = $envelope->last(TransportNamesStamp::class);
        $transportNames = $transportNamesStamp?->getTransportNames() ?? [];
        if ($message instanceof SendEmailMessage || in_array(DatabaseTransport::NAME, $transportNames, \true)) {
            yield DatabaseTransport::NAME => $this->databaseTransport;
        }
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\SendHeron\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * Creates SendHeron API transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class SendHeronTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        if ($dsn->getScheme() !== 'sendheron+api') {
            throw new UnsupportedSchemeException($dsn, 'sendheron', $this->getSupportedSchemes());
        }
        return (new \JooosiMail\Mail\Transport\Bridge\SendHeron\Transport\SendHeronApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))->setHost($dsn->getHost() === 'default' ? null : $dsn->getHost())->setPort($dsn->getPort());
    }
    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['sendheron+api'];
    }
}

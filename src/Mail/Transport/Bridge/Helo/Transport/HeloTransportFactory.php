<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Helo\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates Helo API transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class HeloTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        if ($dsn->getScheme() !== 'helo+api') {
            throw new UnsupportedSchemeException($dsn, 'helo', $this->getSupportedSchemes());
        }

        return (new HeloApiTransport($this->getUser($dsn), $dsn->getOption('channel_id'), $this->client, $this->dispatcher, $this->logger))
            ->setHost($dsn->getHost() === 'default' ? null : $dsn->getHost())
            ->setPort($dsn->getPort());
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['helo+api'];
    }
}

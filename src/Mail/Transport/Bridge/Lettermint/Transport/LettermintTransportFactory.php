<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Lettermint\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates Lettermint API transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class LettermintTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        if ($dsn->getScheme() !== 'lettermint+api') {
            throw new UnsupportedSchemeException($dsn, 'lettermint', $this->getSupportedSchemes());
        }

        return (new LettermintApiTransport($this->getUser($dsn), $dsn->getOption('route'), $this->client, $this->dispatcher, $this->logger))
            ->setHost($dsn->getHost() === 'default' ? null : $dsn->getHost())
            ->setPort($dsn->getPort());
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['lettermint+api'];
    }
}

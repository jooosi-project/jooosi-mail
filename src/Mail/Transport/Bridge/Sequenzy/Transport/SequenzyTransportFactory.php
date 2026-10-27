<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Sequenzy\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates Sequenzy API transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class SequenzyTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        if ($dsn->getScheme() !== 'sequenzy+api') {
            throw new UnsupportedSchemeException($dsn, 'sequenzy', $this->getSupportedSchemes());
        }

        return (new SequenzyApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
            ->setHost($dsn->getHost() === 'default' ? null : $dsn->getHost())
            ->setPort($dsn->getPort());
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['sequenzy+api'];
    }
}

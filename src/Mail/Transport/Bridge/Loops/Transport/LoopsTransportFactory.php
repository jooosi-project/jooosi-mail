<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Loops\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates Loops API transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class LoopsTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        if ($dsn->getScheme() !== 'loops+api') {
            throw new UnsupportedSchemeException($dsn, 'loops', $this->getSupportedSchemes());
        }

        $transactionalId = $dsn->getOption('transactional_id');
        if (! is_string($transactionalId) || $transactionalId === '') {
            throw new IncompleteDsnException('A transactional_id option is required for Loops.');
        }

        return (new LoopsApiTransport($this->getUser($dsn), $transactionalId, $this->client, $this->dispatcher, $this->logger))
            ->setHost($dsn->getHost() === 'default' ? null : $dsn->getHost())
            ->setPort($dsn->getPort());
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['loops+api'];
    }
}

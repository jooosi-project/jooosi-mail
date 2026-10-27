<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Iterable\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates Iterable API transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class IterableTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        if ($dsn->getScheme() !== 'iterable+api') {
            throw new UnsupportedSchemeException($dsn, 'iterable', $this->getSupportedSchemes());
        }

        $campaignId = $dsn->getOption('campaign_id');
        if (! is_scalar($campaignId) || (int) $campaignId < 1) {
            throw new IncompleteDsnException('A positive campaign_id option is required for Iterable.');
        }

        return (new IterableApiTransport($this->getUser($dsn), (int) $campaignId, (string) $dsn->getOption('region', 'us'), $this->client, $this->dispatcher, $this->logger))
            ->setHost($dsn->getHost() === 'default' ? null : $dsn->getHost())
            ->setPort($dsn->getPort());
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['iterable+api'];
    }
}

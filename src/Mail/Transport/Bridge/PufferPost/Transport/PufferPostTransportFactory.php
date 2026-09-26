<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\PufferPost\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * PufferPost custom transport factory.
 *
 * @since 0.1.0
 */
#[Service]
#[TransportFactory]
final class PufferPostTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ($dsn->getScheme() === 'pufferpost+api') {
            $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
            return (new \JooosiMail\Mail\Transport\Bridge\PufferPost\Transport\PufferPostApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($dsn->getPort());
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new UnsupportedSchemeException($dsn, 'pufferpost', $this->getSupportedSchemes());
    }
    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['pufferpost+api'];
    }
}

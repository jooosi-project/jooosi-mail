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
 * Creates Helo API and SMTP transports.
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
        $scheme = $dsn->getScheme();
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return match ($scheme) {
            'helo+api' => (new HeloApiTransport($this->getUser($dsn), $dsn->getOption('channel_id'), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'helo+smtp' => new HeloSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), false, $port, $this->dispatcher, $this->logger),
            'helo+smtps' => new HeloSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), true, $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'helo', $this->getSupportedSchemes()),
        };
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['helo+api', 'helo+smtp', 'helo+smtps'];
    }
}

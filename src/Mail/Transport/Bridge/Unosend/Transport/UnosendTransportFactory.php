<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Unosend\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates Unosend API and SMTP transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class UnosendTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return match ($scheme) {
            'unosend+api' => (new UnosendApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'unosend+smtp' => new UnosendSmtpTransport($this->getPassword($dsn), false, $port, $this->dispatcher, $this->logger),
            'unosend+smtps' => new UnosendSmtpTransport($this->getPassword($dsn), true, $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'unosend', $this->getSupportedSchemes()),
        };
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['unosend+api', 'unosend+smtp', 'unosend+smtps'];
    }
}

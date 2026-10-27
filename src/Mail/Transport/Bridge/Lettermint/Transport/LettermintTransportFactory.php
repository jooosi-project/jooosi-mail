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
 * Creates Lettermint API and SMTP transports.
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
        $scheme = $dsn->getScheme();

        return match ($scheme) {
            'lettermint+api' => (new LettermintApiTransport($this->getUser($dsn), $dsn->getOption('route'), $this->client, $this->dispatcher, $this->logger))
                ->setHost($dsn->getHost() === 'default' ? null : $dsn->getHost())
                ->setPort($dsn->getPort()),
            'lettermint+smtp' => new LettermintSmtpTransport($this->getPassword($dsn), $dsn->getOption('route'), false, $dsn->getPort(), $this->dispatcher, $this->logger),
            'lettermint+smtps' => new LettermintSmtpTransport($this->getPassword($dsn), $dsn->getOption('route'), true, $dsn->getPort(), $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'lettermint', $this->getSupportedSchemes()),
        };
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['lettermint+api', 'lettermint+smtp', 'lettermint+smtps'];
    }
}

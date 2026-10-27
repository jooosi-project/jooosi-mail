<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\JetEmail\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates JetEmail API and SMTP transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class JetEmailTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return match ($scheme) {
            'jetemail+api' => (new JetEmailApiTransport($this->getUser($dsn), (string) $dsn->getOption('region', 'global'), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'jetemail+smtp' => new JetEmailSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), (string) $dsn->getOption('region', 'global'), false, $port, $this->dispatcher, $this->logger),
            'jetemail+smtps' => new JetEmailSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), (string) $dsn->getOption('region', 'global'), true, $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'jetemail', $this->getSupportedSchemes()),
        };
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['jetemail+api', 'jetemail+smtp', 'jetemail+smtps'];
    }
}

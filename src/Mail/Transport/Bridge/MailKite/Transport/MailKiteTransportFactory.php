<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\MailKite\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * MailKite custom transport factory.
 *
 * @since 0.1.0
 */
#[Service]
#[TransportFactory]
final class MailKiteTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $port = $dsn->getPort();
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();

        return match ($scheme) {
            'mailkite+api' => (new MailKiteApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'mailkite+smtp' => new MailKiteSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), false, $port, $this->dispatcher, $this->logger),
            'mailkite+smtps' => new MailKiteSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), true, $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'mailkite', $this->getSupportedSchemes()),
        };
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['mailkite+api', 'mailkite+smtp', 'mailkite+smtps'];
    }
}

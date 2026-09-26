<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\SmtpCom\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
#[Service]
#[TransportFactory]
final class SmtpComTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $apiKey = $this->getUser($dsn);
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();
        return match ($scheme) {
            'smtpcom+api' => (new \JooosiMail\Mail\Transport\Bridge\SmtpCom\Transport\SmtpComApiTransport($apiKey, $dsn->getOption('channel'), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'smtpcom+smtp', 'smtpcom+smtps' => new \JooosiMail\Mail\Transport\Bridge\SmtpCom\Transport\SmtpComSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'smtpcom', $this->getSupportedSchemes()),
        };
    }
    protected function getSupportedSchemes(): array
    {
        return ['smtpcom+api', 'smtpcom+smtp', 'smtpcom+smtps'];
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\ElasticEmail\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
#[Service]
#[TransportFactory]
final class ElasticEmailTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();
        return match ($scheme) {
            'elasticemail+api' => (new \JooosiMail\Mail\Transport\Bridge\ElasticEmail\Transport\ElasticEmailApiTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'elasticemail+smtp', 'elasticemail+smtps' => new \JooosiMail\Mail\Transport\Bridge\ElasticEmail\Transport\ElasticEmailSmtpTransport($user, $this->getPassword($dsn), $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'elasticemail', $this->getSupportedSchemes()),
        };
    }
    protected function getSupportedSchemes(): array
    {
        return ['elasticemail+api', 'elasticemail+smtp', 'elasticemail+smtps'];
    }
}

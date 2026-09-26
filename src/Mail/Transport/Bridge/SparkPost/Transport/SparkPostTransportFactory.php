<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\SparkPost\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
#[Service]
#[TransportFactory]
final class SparkPostTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $apiKey = $this->getUser($dsn) ?? $this->getPassword($dsn);
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();
        $region = $dsn->getOption('region');
        return match ($scheme) {
            'sparkpost+api' => (new \JooosiMail\Mail\Transport\Bridge\SparkPost\Transport\SparkPostApiTransport($apiKey, $region, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'sparkpost+smtp', 'sparkpost+smtps' => new \JooosiMail\Mail\Transport\Bridge\SparkPost\Transport\SparkPostSmtpTransport($apiKey, $region, $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'sparkpost', $this->getSupportedSchemes()),
        };
    }
    protected function getSupportedSchemes(): array
    {
        return ['sparkpost+api', 'sparkpost+smtp', 'sparkpost+smtps'];
    }
}

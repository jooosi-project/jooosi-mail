<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\SendLayer\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
#[Service]
#[TransportFactory]
final class SendLayerTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();
        return match ($scheme) {
            'sendlayer+api' => (new \JooosiMail\Mail\Transport\Bridge\SendLayer\Transport\SendLayerApiTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'sendlayer+smtp', 'sendlayer+smtps' => new \JooosiMail\Mail\Transport\Bridge\SendLayer\Transport\SendLayerSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'sendlayer', $this->getSupportedSchemes()),
        };
    }
    protected function getSupportedSchemes(): array
    {
        return ['sendlayer+api', 'sendlayer+smtp', 'sendlayer+smtps'];
    }
}

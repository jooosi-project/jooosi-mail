<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Smtp2go\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
#[Service]
#[TransportFactory]
final class Smtp2goTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $port = $dsn->getPort();
        $region = $dsn->getOption('region');
        return match ($scheme) {
            'smtp2go+api' => new \JooosiMail\Mail\Transport\Bridge\Smtp2go\Transport\Smtp2goApiTransport($user, $region, $this->client, $this->dispatcher, $this->logger),
            'smtp2go+smtp', 'smtp2go+smtps' => new \JooosiMail\Mail\Transport\Bridge\Smtp2go\Transport\Smtp2goSmtpTransport($user, $this->getPassword($dsn), $region, $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'smtp2go', $this->getSupportedSchemes()),
        };
    }
    protected function getSupportedSchemes(): array
    {
        return ['smtp2go+api', 'smtp2go+smtp', 'smtp2go+smtps'];
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\TurboSmtp\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * TurboSMTP custom transport factory.
 *
 * @since 0.1.0
 */
#[Service]
#[TransportFactory]
final class TurboSmtpTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        if ($scheme === 'turbosmtp+api') {
            return (new \JooosiMail\Mail\Transport\Bridge\TurboSmtp\Transport\TurboSmtpApiTransport($this->getUser($dsn), $this->getPassword($dsn), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($dsn->getPort());
        }
        if ($scheme === 'turbosmtp+smtp') {
            return new \JooosiMail\Mail\Transport\Bridge\TurboSmtp\Transport\TurboSmtpSmtpTransport($host ?? 'pro.turbo-smtp.com', $this->getUser($dsn), $this->getPassword($dsn), $this->dispatcher, $this->logger);
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new UnsupportedSchemeException($dsn, 'turbosmtp', $this->getSupportedSchemes());
    }
    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['turbosmtp+api', 'turbosmtp+smtp'];
    }
}

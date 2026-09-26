<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\ZeptoMail\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * ZeptoMail custom transport factory.
 *
 * @link https://www.zoho.com/zeptomail/help/api/email-sending.html
 * @link https://www.zoho.com/zeptomail/help/smtp-home.html
 *
 * @since 0.1.0
 */
#[Service]
#[TransportFactory]
final class ZeptoMailTransportFactory extends AbstractTransportFactory
{
    /**
     * @since 0.1.0
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $password = $dsn->getPassword();
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();
        return match ($scheme) {
            'zeptomail+api' => (new \JooosiMail\Mail\Transport\Bridge\ZeptoMail\Transport\ZeptoMailApiTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'zeptomail', 'zeptomail+smtp' => new \JooosiMail\Mail\Transport\Bridge\ZeptoMail\Transport\ZeptoMailSmtpTransport($password === null ? 'emailapikey' : $user, $password ?? $user, $port, $this->dispatcher, $this->logger),
            'zeptomail+smtps' => new \JooosiMail\Mail\Transport\Bridge\ZeptoMail\Transport\ZeptoMailSmtpTransport($password === null ? 'emailapikey' : $user, $password ?? $user, $port ?? 465, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'zeptomail', $this->getSupportedSchemes()),
        };
    }
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    protected function getSupportedSchemes(): array
    {
        return ['zeptomail', 'zeptomail+api', 'zeptomail+smtp', 'zeptomail+smtps'];
    }
}

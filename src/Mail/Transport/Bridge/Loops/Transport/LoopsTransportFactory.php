<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Loops\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\IncompleteDsnException;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * Creates Loops API and SMTP transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class LoopsTransportFactory extends AbstractTransportFactory
{
    /** @since 1.0.13 */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        if (!in_array($scheme, $this->getSupportedSchemes(), \true)) {
            throw new UnsupportedSchemeException($dsn, 'loops', $this->getSupportedSchemes());
        }
        $transactionalId = $dsn->getOption('transactional_id');
        if (!is_string($transactionalId) || $transactionalId === '') {
            throw new IncompleteDsnException('A transactional_id option is required for Loops.');
        }
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();
        return match ($scheme) {
            'loops+api' => (new \JooosiMail\Mail\Transport\Bridge\Loops\Transport\LoopsApiTransport($this->getUser($dsn), $transactionalId, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'loops+smtp' => new \JooosiMail\Mail\Transport\Bridge\Loops\Transport\LoopsSmtpTransport($this->getPassword($dsn), $transactionalId, $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'loops', $this->getSupportedSchemes()),
        };
    }
    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['loops+api', 'loops+smtp'];
    }
}

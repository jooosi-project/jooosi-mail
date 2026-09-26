<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Bird\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Bird custom transport factory.
 *
 * @since 0.1.0
 */
#[Service]
#[TransportFactory]
final class BirdTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $accessKey = $this->getUser($dsn) ?? $this->getPassword($dsn);
        $workspaceId = $dsn->getOption('workspace_id') ?? '';
        $host = $dsn->getHost() === 'default' ? null : $dsn->getHost();
        $port = $dsn->getPort();
        $region = $dsn->getOption('region');

        return match ($scheme) {
            'bird+api' => (new BirdApiTransport($accessKey, $workspaceId, $region, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port),
            'bird+smtp' => new BirdSmtpTransport($accessKey, false, $port, $this->dispatcher, $this->logger),
            'bird+smtps' => new BirdSmtpTransport($accessKey, true, $port, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => 
                throw new UnsupportedSchemeException($dsn, 'bird', $this->getSupportedSchemes()),
        };
    }

    protected function getSupportedSchemes(): array
    {
        return ['bird+api', 'bird+smtp', 'bird+smtps'];
    }
}

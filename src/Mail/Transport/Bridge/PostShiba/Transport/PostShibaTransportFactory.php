<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\PostShiba\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Override;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates PostShiba API and SMTP transports.
 *
 * @since 1.0.13
 */
#[Service]
#[TransportFactory]
final class PostShibaTransportFactory extends AbstractTransportFactory
{
    /**
     * @since 1.0.13
     */
    #[Override]
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'postshiba+api' => new PostShibaApiTransport(
                $this->getUser($dsn),
                $dsn->getOption('cluster_id'),
                $this->client,
                $this->dispatcher,
                $this->logger,
            ),
            'postshiba+smtp' => new PostShibaSmtpTransport(
                $this->getUser($dsn),
                $this->getPassword($dsn),
                $this->dispatcher,
                $this->logger,
            ),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'postshiba', $this->getSupportedSchemes()),
        };
    }

    /**
     * @return list<string>
     *
     * @since 1.0.13
     */
    protected function getSupportedSchemes(): array
    {
        return ['postshiba+api', 'postshiba+smtp'];
    }
}

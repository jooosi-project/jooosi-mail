<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\PostShiba\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use Override;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
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
            'postshiba+api' => new \JooosiMail\Mail\Transport\Bridge\PostShiba\Transport\PostShibaApiTransport($this->getUser($dsn), $dsn->getOption('cluster_id'), $this->client, $this->dispatcher, $this->logger),
            'postshiba+smtp' => new \JooosiMail\Mail\Transport\Bridge\PostShiba\Transport\PostShibaSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $this->dispatcher, $this->logger),
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

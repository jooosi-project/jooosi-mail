<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Gmail\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMail\Mail\Transport\Bridge\Gmail\GmailTokenManager;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\IncompleteDsnException;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * Gmail custom transport factory.
 *
 * @since 0.1.0
 */
#[Service]
#[TransportFactory]
final class GmailTransportFactory extends AbstractTransportFactory
{
    /**
     * @since 0.1.0
     */
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'gmail+api' => $this->createApiTransport($dsn),
            'gmail', 'gmail+smtp', 'gmail+smtps' => new \JooosiMail\Mail\Transport\Bridge\Gmail\Transport\GmailSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'gmail', $this->getSupportedSchemes()),
        };
    }
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    protected function getSupportedSchemes(): array
    {
        return ['gmail', 'gmail+api', 'gmail+smtp', 'gmail+smtps'];
    }
    /**
     * @since 0.1.0
     */
    private function createApiTransport(Dsn $dsn): TransportInterface
    {
        $userEmail = trim((string) $dsn->getOption('user'));
        if ($userEmail === '') {
            throw new IncompleteDsnException('Transport "gmail+api" requires the "user" option to specify the delegated sender address.');
        }
        $privateKey = base64_decode($this->getPassword($dsn), \true);
        if (!is_string($privateKey) || $privateKey === '') {
            throw new IncompleteDsnException('Transport "gmail+api" requires a valid base64-encoded private key.');
        }
        $tokenManager = new GmailTokenManager($this->getUser($dsn), $privateKey, $userEmail, $this->client);
        return new \JooosiMail\Mail\Transport\Bridge\Gmail\Transport\GmailApiTransport($tokenManager, $userEmail, $this->client, $this->dispatcher, $this->logger);
    }
}

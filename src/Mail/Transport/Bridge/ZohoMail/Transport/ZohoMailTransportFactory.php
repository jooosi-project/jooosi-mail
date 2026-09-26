<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\ZohoMail\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * Zoho Mail custom transport factory.
 *
 * @link https://www.zoho.com/mail/help/zoho-smtp.html
 *
 * @since 0.1.0
 */
#[Service]
#[TransportFactory]
final class ZohoMailTransportFactory extends AbstractTransportFactory
{
    /**
     * @since 0.1.0
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $username = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $port = $dsn->getPort();
        $accountType = $dsn->getOption('account_type');
        return match ($scheme) {
            'zohomail', 'zohomail+smtp' => new \JooosiMail\Mail\Transport\Bridge\ZohoMail\Transport\ZohoMailSmtpTransport($username, $password, $accountType, $port, $this->dispatcher, $this->logger),
            'zohomail+smtps' => new \JooosiMail\Mail\Transport\Bridge\ZohoMail\Transport\ZohoMailSmtpTransport($username, $password, $accountType, $port ?? 465, $this->dispatcher, $this->logger),
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default => throw new UnsupportedSchemeException($dsn, 'zohomail', $this->getSupportedSchemes()),
        };
    }
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    protected function getSupportedSchemes(): array
    {
        return ['zohomail', 'zohomail+smtp', 'zohomail+smtps'];
    }
}

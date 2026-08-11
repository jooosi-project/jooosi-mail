<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * Adapted from Symfony Mailer bridge source licensed under the MIT License.
 * See documentation/THIRD_PARTY_NOTICES.md for the original license notice.
 */
namespace JooosiMail\Mail\Transport\Bridge\AhaSend\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * @author Farhad Hedayatifard <farhad@ahasend.com>
 */
#[Service]
#[TransportFactory]
final class AhaSendTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $transport = null;
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        if ('ahasend+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();
            $transport = (new \JooosiMail\Mail\Transport\Bridge\AhaSend\Transport\AhaSendApiTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }
        if ('ahasend+smtp' === $scheme || 'ahasend' === $scheme) {
            $password = $this->getPassword($dsn);
            $transport = new \JooosiMail\Mail\Transport\Bridge\AhaSend\Transport\AhaSendSmtpTransport($user, $password, $this->dispatcher, $this->logger);
        }
        if (null === $transport) {
            throw new UnsupportedSchemeException($dsn, 'ahasend', $this->getSupportedSchemes());
        }
        return $transport;
    }
    protected function getSupportedSchemes(): array
    {
        return ['ahasend', 'ahasend+api', 'ahasend+smtp'];
    }
}

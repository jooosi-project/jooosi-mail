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
namespace JooosiMail\Mail\Transport\Bridge\Postal\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\IncompleteDsnException;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
#[Service]
#[TransportFactory]
final class PostalTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        if (!\in_array($scheme, $this->getSupportedSchemes(), \true)) {
            throw new UnsupportedSchemeException($dsn, 'postal', $this->getSupportedSchemes());
        }
        $host = $dsn->getHost();
        $port = $dsn->getPort();
        $apiToken = $this->getPassword($dsn);
        return (new \JooosiMail\Mail\Transport\Bridge\Postal\Transport\PostalApiTransport($apiToken, $host, $this->client, $this->dispatcher, $this->logger))->setPort($port);
    }
    protected function getSupportedSchemes(): array
    {
        return ['postal', 'postal+api'];
    }
    protected function getPassword(Dsn $dsn): string
    {
        return $dsn->getPassword() ?? $dsn->getUser() ?? throw new IncompleteDsnException('Password is not set.');
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * Adapted from Symfony Mailer bridge source licensed under the MIT License.
 * See documentation/THIRD_PARTY_NOTICES.md for the original license notice.
 */

namespace JooosiMail\Mail\Transport\Bridge\Azure\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

#[Service]
#[TransportFactory]
final class AzureTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!\in_array($scheme, ['azure+api', 'azure'], true)) {
            throw new UnsupportedSchemeException($dsn, 'azure', $this->getSupportedSchemes());
        }

        $user = $this->getUser($dsn); // resourceName
        $password = $this->getPassword($dsn); // apiKey
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $apiVersion = $dsn->getOption('api_version', '2023-03-31');
        $disableTracking = (bool) $dsn->getOption('disable_tracking', false);

        return (new AzureApiTransport($password, $user, $disableTracking, $apiVersion, $this->client, $this->dispatcher, $this->logger))->setHost($host);
    }

    protected function getSupportedSchemes(): array
    {
        return ['azure', 'azure+api'];
    }
}

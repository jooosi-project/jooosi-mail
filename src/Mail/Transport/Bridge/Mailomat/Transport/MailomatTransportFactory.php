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
namespace JooosiMail\Mail\Transport\Bridge\Mailomat\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
#[Service]
#[TransportFactory]
final class MailomatTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $schema = $dsn->getScheme();
        if ('mailomat+api' === $schema) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();
            return (new \JooosiMail\Mail\Transport\Bridge\Mailomat\Transport\MailomatApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }
        if (\in_array($schema, ['mailomat+smtp', 'mailomat+smtps', 'mailomat'], \true)) {
            return new \JooosiMail\Mail\Transport\Bridge\Mailomat\Transport\MailomatSmtpTransport($dsn->getUser(), $dsn->getPassword(), $this->dispatcher, $this->logger);
        }
        throw new UnsupportedSchemeException($dsn, 'mailomat', $this->getSupportedSchemes());
    }
    protected function getSupportedSchemes(): array
    {
        return ['mailomat', 'mailomat+api', 'mailomat+smtp', 'mailomat+smtps'];
    }
}

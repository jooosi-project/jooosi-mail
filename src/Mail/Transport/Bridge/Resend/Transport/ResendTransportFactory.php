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
namespace JooosiMail\Mail\Transport\Bridge\Resend\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
#[Service]
#[TransportFactory]
final class ResendTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'resend', 'resend+smtp' => new \JooosiMail\Mail\Transport\Bridge\Resend\Transport\ResendSmtpTransport($this->getPassword($dsn), $this->dispatcher, $this->logger),
            'resend+api' => (new \JooosiMail\Mail\Transport\Bridge\Resend\Transport\ResendApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())->setPort($dsn->getPort()),
            default => throw new UnsupportedSchemeException($dsn, 'resend', $this->getSupportedSchemes()),
        };
    }
    protected function getSupportedSchemes(): array
    {
        return ['resend', 'resend+smtp', 'resend+api'];
    }
}

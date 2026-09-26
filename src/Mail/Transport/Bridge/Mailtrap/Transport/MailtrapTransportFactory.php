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
namespace JooosiMail\Mail\Transport\Bridge\Mailtrap\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\Dsn;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\TransportInterface;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[Service]
#[TransportFactory]
final class MailtrapTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        if ('mailtrap+api' === $scheme || 'mailtrap+sandbox' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();
            $inboxId = 'mailtrap+sandbox' === $scheme ? $dsn->getOption('inboxId') : null;
            $inboxId = $inboxId === null ? null : (int) $inboxId;
            return (new \JooosiMail\Mail\Transport\Bridge\Mailtrap\Transport\MailtrapApiTransport($user, $this->client, $this->dispatcher, $this->logger, $inboxId))->setHost($host)->setPort($port);
        }
        if ('mailtrap+smtp' === $scheme || 'mailtrap+smtps' === $scheme || 'mailtrap' === $scheme) {
            return new \JooosiMail\Mail\Transport\Bridge\Mailtrap\Transport\MailtrapSmtpTransport($user, $this->dispatcher, $this->logger);
        }
        throw new UnsupportedSchemeException($dsn, 'mailtrap', $this->getSupportedSchemes());
    }
    protected function getSupportedSchemes(): array
    {
        return ['mailtrap', 'mailtrap+api', 'mailtrap+sandbox', 'mailtrap+smtp', 'mailtrap+smtps'];
    }
}

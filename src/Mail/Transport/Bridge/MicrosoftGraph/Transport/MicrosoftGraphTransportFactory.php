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

namespace JooosiMail\Mail\Transport\Bridge\MicrosoftGraph\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;

use JooosiMail\Mail\Transport\Bridge\MicrosoftGraph\TokenManager;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates Microsoft Graph transports.
 *
 * @since 1.0.8
 */
#[Service]
#[TransportFactory]
final class MicrosoftGraphTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('microsoftgraph+api' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'microsoft graph api', $this->getSupportedSchemes());
        }

        if (null === $tenantId = $dsn->getOption('tenantId')) {
            throw new IncompleteDsnException('Transport "microsoftgraph+api" requires the "tenant" option.');
        }

        $graphEndpoint = $dsn->getHost();
        $authEndpoint = $dsn->getOption('authEndpoint');
        if ('default' === $graphEndpoint) {
            $graphEndpoint = 'graph.microsoft.com';
            if (null === $authEndpoint) {
                $authEndpoint = 'login.microsoftonline.com';
            }
        }

        if (null === $authEndpoint) {
            throw new IncompleteDsnException('Transport "microsoftgraph+api" requires the "authEndpoint" option when not using the default graph endpoint.');
        }

        if (preg_match('#^https?://#', $authEndpoint)) {
            throw new InvalidArgumentException('Auth endpoint needs to be provided without "http(s)://".');
        }

        if (preg_match('#^https?://#', $graphEndpoint)) {
            throw new InvalidArgumentException('Graph endpoint needs to be provided without "http(s)://".');
        }

        $tokenManager = new TokenManager($graphEndpoint, $authEndpoint, $tenantId, $this->getUser($dsn), $this->getPassword($dsn), $this->client);

        $noSave = filter_var($dsn->getOption('noSave', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        return new MicrosoftGraphApiTransport($graphEndpoint, $tokenManager, $noSave, $this->client, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['microsoftgraph+api'];
    }
}

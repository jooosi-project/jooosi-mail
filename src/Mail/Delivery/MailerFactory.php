<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Delivery;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionDsnResolver;
use JooosiMail\Mail\Transport\TransportRegistry;
use Symfony\Component\Mailer\Mailer;

/**
 * Creates mailer instances for a connection.
 *
 * @since 0.1.0
 */
#[Service]
final class MailerFactory
{
    public function __construct(
        private readonly ConnectionDsnResolver $connectionDsnResolver,
        private readonly TransportRegistry $transportRegistry,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function create(Connection $connection): Mailer
    {
        return new Mailer($this->transportRegistry->create($this->connectionDsnResolver->resolve($connection)));
    }
}

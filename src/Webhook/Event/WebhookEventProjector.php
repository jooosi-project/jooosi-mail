<?php

declare(strict_types=1);

namespace JooosiMail\Webhook\Event;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;

/**
 * Projects normalized webhook events into WordPress hooks.
 *
 * @since 0.1.0
 */
#[Service]
final class WebhookEventProjector
{
    public function __construct(
        private readonly EventPublisherInterface $eventPublisher,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function project(WebhookEvent $event): void
    {
        $this->eventPublisher->doAction('a!jooosi-mail/webhook:event', $event);
        $this->eventPublisher->doAction('a!jooosi-mail/webhook:event.' . $event->eventType, $event);
    }
}

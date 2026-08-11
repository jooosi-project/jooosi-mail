<?php

declare(strict_types=1);

namespace JooosiMail\Queue\Bus;

use JooosiMail\Discovery\Attribute\Service;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;

/**
 * Builds the Jooosi Mail Messenger bus.
 *
 * @since 0.1.0
 */
#[Service]
final class MessageBusFactory
{
    public function __construct(
        private readonly MessageRouter $messageRouter,
        private readonly HandlerLocator $handlerLocator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function create(): MessageBusInterface
    {
        return new MessageBus([
            new SendMessageMiddleware($this->messageRouter, $this->eventDispatcher, true),
            new HandleMessageMiddleware($this->handlerLocator, false),
        ]);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Bus;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Symfony\Component\Messenger\MessageBus;
use JooosiMailDeps\Symfony\Component\Messenger\MessageBusInterface;
use JooosiMailDeps\Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use JooosiMailDeps\Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
/**
 * Builds the Jooosi Mail Messenger bus.
 *
 * @since 0.1.0
 */
#[Service]
final class MessageBusFactory
{
    public function __construct(private readonly \JooosiMail\Queue\Bus\MessageRouter $messageRouter, private readonly \JooosiMail\Queue\Bus\HandlerLocator $handlerLocator, private readonly EventDispatcherInterface $eventDispatcher)
    {
    }
    /**
     * @since 0.1.0
     */
    public function create(): MessageBusInterface
    {
        return new MessageBus([new SendMessageMiddleware($this->messageRouter, $this->eventDispatcher, \true), new HandleMessageMiddleware($this->handlerLocator, \false)]);
    }
}

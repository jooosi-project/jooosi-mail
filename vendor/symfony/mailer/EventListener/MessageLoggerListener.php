<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Mailer\EventListener;

use JooosiMailDeps\Symfony\Component\EventDispatcher\EventSubscriberInterface;
use JooosiMailDeps\Symfony\Component\Mailer\Event\MessageEvent;
use JooosiMailDeps\Symfony\Component\Mailer\Event\MessageEvents;
use JooosiMailDeps\Symfony\Contracts\Service\ResetInterface;
/**
 * Logs Messages.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MessageLoggerListener implements EventSubscriberInterface, ResetInterface
{
    private MessageEvents $events;
    public function __construct()
    {
        $this->events = new MessageEvents();
    }
    /**
     * @return void
     */
    public function reset()
    {
        $this->events = new MessageEvents();
    }
    public function onMessage(MessageEvent $event): void
    {
        $this->events->add($event);
    }
    public function getEvents(): MessageEvents
    {
        return $this->events;
    }
    public static function getSubscribedEvents(): array
    {
        return [MessageEvent::class => ['onMessage', -255]];
    }
}

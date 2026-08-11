<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Messenger\EventListener;

use JooosiMailDeps\Symfony\Component\EventDispatcher\EventSubscriberInterface;
use JooosiMailDeps\Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use JooosiMailDeps\Symfony\Component\Messenger\Event\WorkerRunningEvent;
use JooosiMailDeps\Symfony\Component\Messenger\Event\WorkerStoppedEvent;
/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ResetServicesListener implements EventSubscriberInterface
{
    private ServicesResetter $servicesResetter;
    public function __construct(ServicesResetter $servicesResetter)
    {
        $this->servicesResetter = $servicesResetter;
    }
    public function resetServices(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle()) {
            $this->servicesResetter->reset();
        }
    }
    public function resetServicesAtStop(WorkerStoppedEvent $event): void
    {
        $this->servicesResetter->reset();
    }
    public static function getSubscribedEvents(): array
    {
        return [WorkerRunningEvent::class => ['resetServices', -1024], WorkerStoppedEvent::class => ['resetServicesAtStop', -1024]];
    }
}

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
use JooosiMailDeps\Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use JooosiMailDeps\Symfony\Component\Messenger\Event\WorkerRunningEvent;
use JooosiMailDeps\Symfony\Component\Messenger\Exception\HandlerFailedException;
use JooosiMailDeps\Symfony\Component\Messenger\Exception\StopWorkerExceptionInterface;
/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class StopWorkerOnCustomStopExceptionListener implements EventSubscriberInterface
{
    private bool $stop = \false;
    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $th = $event->getThrowable();
        if ($th instanceof StopWorkerExceptionInterface) {
            $this->stop = \true;
        }
        if ($th instanceof HandlerFailedException) {
            foreach ($th->getWrappedExceptions() as $e) {
                if ($e instanceof StopWorkerExceptionInterface) {
                    $this->stop = \true;
                    break;
                }
            }
        }
    }
    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->stop) {
            $event->getWorker()->stop();
        }
    }
    public static function getSubscribedEvents(): array
    {
        return [WorkerMessageFailedEvent::class => 'onMessageFailed', WorkerRunningEvent::class => 'onWorkerRunning'];
    }
}

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

use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\Console\Command\SignalableCommandInterface;
use JooosiMailDeps\Symfony\Component\EventDispatcher\EventSubscriberInterface;
use JooosiMailDeps\Symfony\Component\Messenger\Event\WorkerStartedEvent;
/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @deprecated since Symfony 6.4, use the {@see SignalableCommandInterface} instead
 */
class StopWorkerOnSignalsListener implements EventSubscriberInterface
{
    private array $signals;
    private ?LoggerInterface $logger;
    public function __construct(?array $signals = null, ?LoggerInterface $logger = null)
    {
        if (null === $signals && \extension_loaded('pcntl')) {
            $signals = [\SIGTERM, \SIGINT];
        }
        $this->signals = $signals ?? [];
        $this->logger = $logger;
    }
    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        foreach ($this->signals as $signal) {
            pcntl_signal($signal, function () use ($event, $signal) {
                $this->logger?->info('Received signal {signal}.', ['signal' => $signal, 'transport_names' => $event->getWorker()->getMetadata()->getTransportNames()]);
                $event->getWorker()->stop();
            });
        }
    }
    public static function getSubscribedEvents(): array
    {
        if (!\function_exists('pcntl_signal') && !\function_exists('JooosiMailDeps\pcntl_signal')) {
            return [];
        }
        return [WorkerStartedEvent::class => ['onWorkerStarted', 100]];
    }
}

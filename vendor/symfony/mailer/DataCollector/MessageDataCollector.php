<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Mailer\DataCollector;

use JooosiMailDeps\Symfony\Component\HttpFoundation\Request;
use JooosiMailDeps\Symfony\Component\HttpFoundation\Response;
use JooosiMailDeps\Symfony\Component\HttpKernel\DataCollector\DataCollector;
use JooosiMailDeps\Symfony\Component\Mailer\Event\MessageEvents;
use JooosiMailDeps\Symfony\Component\Mailer\EventListener\MessageLoggerListener;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class MessageDataCollector extends DataCollector
{
    private MessageEvents $events;
    public function __construct(MessageLoggerListener $logger)
    {
        $this->events = $logger->getEvents();
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data['events'] = $this->events;
    }
    public function getEvents(): MessageEvents
    {
        return $this->data['events'];
    }
    /**
     * @internal
     */
    public function base64Encode(string $data): string
    {
        return base64_encode($data);
    }
    public function reset(): void
    {
        $this->data = [];
    }
    public function getName(): string
    {
        return 'mailer';
    }
}

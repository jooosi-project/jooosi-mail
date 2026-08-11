<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiMailDeps\Symfony\Component\Mailer\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Psr\Log\NullLogger;
use JooosiMailDeps\Symfony\Bridge\Twig\Mime\TemplatedEmail;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Event\FailedMessageEvent;
use JooosiMailDeps\Symfony\Component\Mailer\Event\MessageEvent;
use JooosiMailDeps\Symfony\Component\Mailer\Event\SentMessageEvent;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\LogicException;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mime\Address;
use JooosiMailDeps\Symfony\Component\Mime\BodyRendererInterface;
use JooosiMailDeps\Symfony\Component\Mime\RawMessage;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractTransport implements TransportInterface
{
    private ?EventDispatcherInterface $dispatcher;
    private LoggerInterface $logger;
    private float $rate = 0;
    private float $lastSent = 0;
    public function __construct(?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger ?? new NullLogger();
    }
    /**
     * Sets the maximum number of messages to send per second (0 to disable).
     *
     * @return $this
     */
    public function setMaxPerSecond(float $rate): static
    {
        if (0 >= $rate) {
            $rate = 0;
        }
        $this->rate = $rate;
        $this->lastSent = 0;
        return $this;
    }
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $message = clone $message;
        $envelope = null !== $envelope ? clone $envelope : Envelope::create($message);
        try {
            if (!$this->dispatcher) {
                $sentMessage = new SentMessage($message, $envelope);
                $this->doSend($sentMessage);
                return $sentMessage;
            }
            $event = new MessageEvent($message, $envelope, (string) $this);
            $this->dispatcher->dispatch($event);
            if ($event->isRejected()) {
                return null;
            }
            $envelope = $event->getEnvelope();
            $message = $event->getMessage();
            if ($message instanceof TemplatedEmail && !$message->isRendered()) {
                throw new LogicException(\sprintf('You must configure a "%s" when a "%s" instance has a text or HTML template set.', BodyRendererInterface::class, get_debug_type($message)));
            }
            $sentMessage = new SentMessage($message, $envelope);
            try {
                $this->doSend($sentMessage);
            } catch (\Throwable $error) {
                $this->dispatcher->dispatch(new FailedMessageEvent($message, $error));
                $this->checkThrottling();
                throw $error;
            }
            $this->dispatcher->dispatch(new SentMessageEvent($sentMessage));
            return $sentMessage;
        } finally {
            $this->checkThrottling();
        }
    }
    abstract protected function doSend(SentMessage $message): void;
    /**
     * @param Address[] $addresses
     *
     * @return string[]
     */
    protected function stringifyAddresses(array $addresses): array
    {
        return array_map(static fn(Address $a) => $a->toString(), $addresses);
    }
    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
    private function checkThrottling(): void
    {
        if (0 == $this->rate) {
            return;
        }
        $sleep = 1 / $this->rate - (microtime(\true) - $this->lastSent);
        if (0 < $sleep) {
            $this->logger->debug(\sprintf('Email transport "%s" sleeps for %.2f seconds', __CLASS__, $sleep));
            usleep((int) ($sleep * 1000000));
        }
        $this->lastSent = microtime(\true);
    }
}

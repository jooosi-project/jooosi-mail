<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Bus;

use JooosiMail\Discovery\Attribute\MessageHandler;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use Override;
use JooosiMailDeps\Psr\Container\ContainerInterface;
use ReflectionClass;
use JooosiMailDeps\Symfony\Component\Messenger\Envelope;
use JooosiMailDeps\Symfony\Component\Messenger\Handler\HandlerDescriptor;
use JooosiMailDeps\Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
/**
 * Resolves handler services for Messenger messages.
 *
 * @since 0.1.0
 */
#[Service]
final class HandlerLocator implements HandlersLocatorInterface
{
    public function __construct(private readonly DiscoveryManifest $manifest, private readonly ContainerInterface $container)
    {
    }
    /**
     * @return iterable<int, HandlerDescriptor>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getHandlers(Envelope $envelope): iterable
    {
        $message = $envelope->getMessage();
        foreach ($this->manifest->messageHandlers as $className) {
            $reflectionClass = new ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes(MessageHandler::class);
            $attribute = $attributes[0] ?? null;
            if ($attribute === null) {
                continue;
            }
            /** @var MessageHandler $handler */
            $handler = $attribute->newInstance();
            if (!is_a($message, $handler->messageClass)) {
                continue;
            }
            yield new HandlerDescriptor($this->container->get($className));
        }
    }
}

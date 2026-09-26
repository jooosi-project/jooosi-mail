<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use JooosiMail\Mail\Connection\Connection;
use JooosiMailDeps\Psr\Container\ContainerInterface;
use ReflectionClass;
use RuntimeException;
/**
 * Resolves the best webhook adapter for a connection.
 *
 * @since 0.1.0
 */
#[Service]
final class WebhookAdapterRegistry
{
    /** @var list<WebhookAdapterInterface>|null */
    private ?array $adapters = null;
    public function __construct(private readonly DiscoveryManifest $manifest, private readonly ContainerInterface $container)
    {
    }
    /**
     * @since 0.1.0
     */
    public function resolve(Connection $connection): \JooosiMail\Webhook\Adapter\WebhookAdapterInterface
    {
        foreach ($this->all() as $adapter) {
            if ($adapter->supports($connection)) {
                return $adapter;
            }
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new RuntimeException(sprintf('No webhook adapter matched connection profile "%s".', $connection->profileKey));
    }
    /**
     * @return list<WebhookAdapterInterface>
     *
     * @since 0.1.0
     */
    private function all(): array
    {
        if (is_array($this->adapters)) {
            return $this->adapters;
        }
        $adapters = [];
        foreach ($this->manifest->services as $className) {
            if (!is_subclass_of($className, \JooosiMail\Webhook\Adapter\WebhookAdapterInterface::class)) {
                continue;
            }
            $reflectionClass = new ReflectionClass($className);
            if ($reflectionClass->isAbstract()) {
                continue;
            }
            $service = $this->container->get($className);
            if ($service instanceof \JooosiMail\Webhook\Adapter\WebhookAdapterInterface) {
                $adapters[] = $service;
            }
        }
        usort($adapters, static fn(\JooosiMail\Webhook\Adapter\WebhookAdapterInterface $left, \JooosiMail\Webhook\Adapter\WebhookAdapterInterface $right): int => $right->getPriority() <=> $left->getPriority());
        $this->adapters = $adapters;
        return $this->adapters;
    }
}

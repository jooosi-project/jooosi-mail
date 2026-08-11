<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\WordPress;

use JooosiMail\Discovery\Attribute\Hook;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * Registers discovered WordPress hooks.
 *
 * @since 0.1.0
 */
final class HookRegistrar
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DiscoveryManifest $manifest,
    ) {
    }

    /**
     * Register all discovered hooks.
     *
     * @since 0.1.0
     */
    public function register(): void
    {
        foreach ($this->manifest->allClasses() as $className) {
            $reflectionClass = new ReflectionClass($className);

            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
                foreach ($reflectionMethod->getAttributes(Hook::class) as $attribute) {
                    /** @var Hook $hook */
                    $hook = $attribute->newInstance();
                    $callback = function (...$args) use ($className, $reflectionMethod): mixed {
                        $service = $this->container->get($className);

                        return $service->{$reflectionMethod->getName()}(...$args);
                    };

                    if ($this->isFilter($hook)) {
                        add_filter($hook->name, $callback, $hook->priority, $hook->acceptedArgs);
                        continue;
                    }

                    add_action($hook->name, $callback, $hook->priority, $hook->acceptedArgs);
                }
            }
        }
    }

    private function isFilter(Hook $hook): bool
    {
        if ($hook->kind === 'filter') {
            return true;
        }

        if ($hook->kind === 'action') {
            return false;
        }

        return str_starts_with($hook->name, 'f!');
    }
}

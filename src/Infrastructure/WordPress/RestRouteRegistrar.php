<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\WordPress;

use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Registers REST routes from discovered controllers.
 *
 * @since 0.1.0
 */
final class RestRouteRegistrar
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DiscoveryManifest $manifest,
    ) {
    }

    /**
     * Register the `rest_api_init` bridge once.
     *
     * @since 0.1.0
     */
    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $this->registerRoutes();
        });
    }

    /**
     * Register all discovered routes.
     *
     * @since 0.1.0
     */
    public function registerRoutes(): void
    {
        foreach ($this->manifest->controllers as $className) {
            $reflectionClass = new ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes(Controller::class);
            $controller = $attributes === [] ? null : $attributes[0]->newInstance();

            if (! $controller instanceof Controller) {
                continue;
            }

            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
                foreach ($reflectionMethod->getAttributes(Route::class) as $attribute) {
                    /** @var Route $route */
                    $route = $attribute->newInstance();

                    register_rest_route(
                        $controller->namespace,
                        $this->normalizeRoutePath($controller->prefix, $route->path),
                        [
                            'methods' => $route->methods,
                            'callback' => function (...$args) use ($className, $reflectionMethod): mixed {
                                $service = $this->container->get($className);

                                return $service->{$reflectionMethod->getName()}(...$args);
                            },
                            'permission_callback' => $this->resolvePermissionCallback($className, $route->permissionCallback),
                            'args' => $route->args,
                        ],
                    );
                }
            }
        }
    }

    private function resolvePermissionCallback(string $className, array|string $permissionCallback): callable|string
    {
        if (is_string($permissionCallback) && method_exists($className, $permissionCallback)) {
            return function (...$args) use ($className, $permissionCallback): mixed {
                $service = $this->container->get($className);

                return $service->{$permissionCallback}(...$args);
            };
        }

        if (is_string($permissionCallback) && str_contains($permissionCallback, '::')) {
            [$targetClassName, $methodName] = explode('::', $permissionCallback, 2);

            return $this->resolveClassPermissionCallback($targetClassName, $methodName, $permissionCallback);
        }

        if (is_array($permissionCallback)) {
            [$targetClassName, $methodName] = $permissionCallback + [null, null];

            if (is_object($targetClassName) && is_string($methodName)) {
                return [$targetClassName, $methodName];
            }

            if (is_string($targetClassName) && is_string($methodName)) {
                return $this->resolveClassPermissionCallback($targetClassName, $methodName, $permissionCallback);
            }
        }

        return $permissionCallback;
    }

    /**
     * @param array{0: class-string|object, 1: string}|string $originalCallback
     *
     * @throws ReflectionException
     */
    private function resolveClassPermissionCallback(string $className, string $methodName, array|string $originalCallback): callable|string
    {
        if (! method_exists($className, $methodName)) {
            return $originalCallback;
        }

        $reflectionMethod = new ReflectionMethod($className, $methodName);

        if ($reflectionMethod->isStatic()) {
            return [$className, $methodName];
        }

        return function (...$args) use ($className, $methodName): mixed {
            $service = $this->container->get($className);

            return $service->{$methodName}(...$args);
        };
    }

    private function normalizeRoutePath(string $prefix, string $path): string
    {
        $prefix = trim($prefix, '/');
        $path = trim($path, '/');

        if ($prefix === '') {
            return '/' . $path;
        }

        if ($path === '') {
            return '/' . $prefix;
        }

        return '/' . $prefix . '/' . $path;
    }
}

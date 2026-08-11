<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\WordPress;

use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use Psr\Container\ContainerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use WP_CLI;

/**
 * Registers discovered WP-CLI commands.
 *
 * @since 0.1.0
 */
final class CommandRegistrar
{
    /**
     * @var string
     */
    private const COMMAND_PREFIX = 'jooosi-mail';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DiscoveryManifest $manifest,
    ) {
    }

    /**
     * Register all discovered commands when WP-CLI is available.
     *
     * @since 0.1.0
     */
    public function register(): void
    {
        if (! defined('WP_CLI') || ! WP_CLI || ! class_exists(WP_CLI::class)) {
            return;
        }

        foreach ($this->manifest->commands as $className) {
            $this->registerClassCommands($className);
        }
    }

    /**
     * @param class-string $className
     *
     * @since 0.1.0
     */
    private function registerClassCommands(string $className): void
    {
        $reflectionClass = new ReflectionClass($className);
        $instance = $this->container->get($className);

        $classAttributes = $reflectionClass->getAttributes(Command::class);
        $classCommand = $this->newCommandInstance($classAttributes[0] ?? null);
        if ($classCommand instanceof Command && $reflectionClass->hasMethod('__invoke')) {
            $this->registerCommand($classCommand, [$instance, '__invoke'], $reflectionClass);
        }

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if ($reflectionMethod->getDeclaringClass()->getName() !== $className) {
                continue;
            }

            $methodAttributes = $reflectionMethod->getAttributes(Command::class);
            $methodCommand = $this->newCommandInstance($methodAttributes[0] ?? null);
            if (! $methodCommand instanceof Command) {
                continue;
            }

            $this->registerCommand($methodCommand, [$instance, $reflectionMethod->getName()], $reflectionClass, $reflectionMethod);
        }
    }

    /**
     * @since 0.1.0
     */
    private function newCommandInstance(?ReflectionAttribute $attribute): ?Command
    {
        if ($attribute === null) {
            return null;
        }

        $command = $attribute->newInstance();

        return $command instanceof Command ? $command : null;
    }

    /**
     * @since 0.1.0
     */
    private function registerCommand(Command $command, callable $callable, ReflectionClass $reflectionClass, ?ReflectionMethod $reflectionMethod = null): void
    {
        $commandName = $this->resolveCommandName($command, $reflectionClass, $reflectionMethod);
        $arguments = $this->buildRegistrationArguments($command);

        WP_CLI::add_command($commandName, $callable, $arguments);

        foreach ($command->aliases as $alias) {
            if ($alias === '' || $alias === $commandName) {
                continue;
            }

            WP_CLI::add_command($alias, $callable, $arguments);
        }
    }

    /**
     * @since 0.1.0
     */
    private function resolveCommandName(Command $command, ReflectionClass $reflectionClass, ?ReflectionMethod $reflectionMethod = null): string
    {
        if (is_string($command->name) && $command->name !== '') {
            return $command->name;
        }

        return $this->generateCommandName($reflectionClass, $reflectionMethod);
    }

    /**
     * @since 0.1.0
     */
    private function generateCommandName(ReflectionClass $reflectionClass, ?ReflectionMethod $reflectionMethod = null): string
    {
        $classSegment = $this->normalizeCommandSegment($reflectionClass->getShortName(), stripCommandSuffix: true);

        if ($reflectionMethod === null || $reflectionMethod->getName() === '__invoke') {
            return sprintf('%s %s', self::COMMAND_PREFIX, $classSegment);
        }

        return sprintf(
            '%s %s:%s',
            self::COMMAND_PREFIX,
            $classSegment,
            $this->normalizeCommandSegment($reflectionMethod->getName(), stripCommandSuffix: false),
        );
    }

    /**
     * @since 0.1.0
     */
    private function normalizeCommandSegment(string $name, bool $stripCommandSuffix): string
    {
        $segment = $name;

        if ($stripCommandSuffix && str_ends_with($segment, 'Command')) {
            $segment = substr($segment, 0, -7);
        }

        if ($segment === '') {
            $segment = $name;
        }

        $segment = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $segment) ?? $segment;

        return strtolower($segment);
    }

    /**
     * @return array<string, string>
     *
     * @since 0.1.0
     */
    private function buildRegistrationArguments(Command $command): array
    {
        $arguments = [
            'shortdesc' => $command->description,
        ];

        if ($command->synopsis !== null) {
            $arguments['synopsis'] = $command->synopsis;
        }

        if ($command->when !== null) {
            $arguments['when'] = $command->when;
        }

        return $arguments;
    }
}

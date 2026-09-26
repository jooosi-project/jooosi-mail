<?php

declare (strict_types=1);
namespace JooosiMail\Discovery\Runtime;

use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Discovery\Attribute\MessageHandler;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Attribute\TransportFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
/**
 * Discovers attributed classes from the plugin's PSR-4 source tree.
 *
 * @since 1.0.8
 */
final class AttributeDiscovery
{
    public function __construct(private readonly string $namespacePrefix, private readonly string $sourceDirectory)
    {
    }
    /**
     * Build an immutable manifest of attributed, instantiable classes.
     *
     * @since 1.0.8
     */
    public function discover(): \JooosiMail\Discovery\Runtime\DiscoveryManifest
    {
        $services = [];
        $controllers = [];
        $commands = [];
        $profiles = [];
        $messageHandlers = [];
        $transportFactories = [];
        foreach ($this->classNames() as $className) {
            if (!class_exists($className)) {
                continue;
            }
            $class = new ReflectionClass($className);
            if (!$class->isInstantiable()) {
                continue;
            }
            $this->recordClass($services, $class, Service::class);
            $this->recordClass($controllers, $class, Controller::class);
            $this->recordClass($profiles, $class, MailProfile::class);
            $this->recordClass($messageHandlers, $class, MessageHandler::class);
            $this->recordClass($transportFactories, $class, TransportFactory::class);
            if ($class->getAttributes(Command::class) !== [] || $this->hasAttributedCommandMethod($class)) {
                $commands[$className] = $className;
            }
        }
        return new \JooosiMail\Discovery\Runtime\DiscoveryManifest(services: $this->sortedValues($services), controllers: $this->sortedValues($controllers), commands: $this->sortedValues($commands), profiles: $this->sortedValues($profiles), messageHandlers: $this->sortedValues($messageHandlers), transportFactories: $this->sortedValues($transportFactories));
    }
    /**
     * @return list<class-string>
     *
     * @since 1.0.8
     */
    private function classNames(): array
    {
        $sourceDirectory = rtrim($this->sourceDirectory, '/\\');
        $prefixLength = strlen($sourceDirectory) + 1;
        $classNames = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDirectory, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relativePath = substr($file->getPathname(), $prefixLength, -4);
            if (!is_string($relativePath) || $relativePath === '') {
                continue;
            }
            $relativeClass = str_replace(['/', '\\'], '\\', $relativePath);
            $classNames[] = rtrim($this->namespacePrefix, '\\') . '\\' . $relativeClass;
        }
        sort($classNames);
        return $classNames;
    }
    /**
     * @param array<class-string, class-string> $classes
     * @param ReflectionClass<object>           $class
     * @param class-string                      $attributeClass
     *
     * @since 1.0.8
     */
    private function recordClass(array &$classes, ReflectionClass $class, string $attributeClass): void
    {
        if ($class->getAttributes($attributeClass) === []) {
            return;
        }
        $className = $class->getName();
        $classes[$className] = $className;
    }
    /**
     * @param ReflectionClass<object> $class
     *
     * @since 1.0.8
     */
    private function hasAttributedCommandMethod(ReflectionClass $class): bool
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            if ($method->getAttributes(Command::class) !== []) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * @param array<class-string, class-string> $classes
     *
     * @return list<class-string>
     *
     * @since 1.0.8
     */
    private function sortedValues(array $classes): array
    {
        $classes = array_values($classes);
        sort($classes);
        return $classes;
    }
}

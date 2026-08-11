<?php

declare (strict_types=1);
namespace JooosiMail\Infrastructure\Container;

use JooosiMailDeps\Doctrine\DBAL\Connection;
use JooosiMail\Bootstrap\Environment;
use JooosiMail\Bootstrap\LifecycleManager;
use JooosiMail\Bootstrap\Paths;
use JooosiMail\Discovery\Runtime\AttributeDiscovery;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use JooosiMail\Infrastructure\Database\DatabaseConnectionFactory;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Infrastructure\Security\SecretCipher;
use JooosiMail\Infrastructure\WordPress\CommandRegistrar;
use JooosiMail\Infrastructure\WordPress\HookRegistrar;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Infrastructure\WordPress\RestRouteRegistrar;
use JooosiMail\Infrastructure\WordPress\WordPressEventPublisher;
use JooosiMail\Mail\Routing\ConnectionHealthPenaltyProviderInterface;
use JooosiMail\Queue\Bus\MessageBusFactory;
use JooosiMail\Webhook\Event\WebhookHealthPenaltyProvider;
use JooosiMailDeps\Psr\Container\ContainerInterface;
use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Psr\Log\NullLogger;
use ReflectionClass;
use Throwable;
use JooosiMailDeps\Symfony\Component\DependencyInjection\ContainerBuilder;
use JooosiMailDeps\Symfony\Component\DependencyInjection\Definition;
use JooosiMailDeps\Symfony\Component\DependencyInjection\Reference;
use JooosiMailDeps\Symfony\Component\EventDispatcher\EventDispatcher;
use JooosiMailDeps\Symfony\Component\HttpClient\HttpClient;
use JooosiMailDeps\Symfony\Component\Messenger\MessageBusInterface;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
/**
 * Builds the Jooosi Mail Symfony container.
 *
 * @since 0.1.0
 */
final class ContainerFactory
{
    public function __construct(private readonly Paths $paths, private readonly Environment $environment)
    {
    }
    /**
     * Build or load the compiled container.
     *
     * @since 0.1.0
     */
    public function build(): ContainerInterface
    {
        $cache = new \JooosiMail\Infrastructure\Container\ContainerCache($this->paths, $this->environment);
        $inspection = $cache->inspect();
        if ($inspection['usable']) {
            try {
                return $cache->load();
            } catch (Throwable) {
                $cache->clear();
            }
        }
        $manifest = $this->discover();
        $builder = new ContainerBuilder();
        $this->registerCoreServices($builder, $manifest);
        $this->registerDiscoveredServices($builder, $manifest);
        $builder->compile();
        $cache->dump($builder);
        try {
            return $cache->load();
        } catch (Throwable) {
            $cache->clear();
            // Keep the current request alive when the dumped PHP file is stale or not reloadable.
            return $builder;
        }
    }
    /**
     * @since 0.1.0
     */
    private function discover(): DiscoveryManifest
    {
        return (new AttributeDiscovery('JooosiMail', $this->paths->srcDir))->discover();
    }
    /**
     * @since 0.1.0
     */
    private function registerCoreServices(ContainerBuilder $builder, DiscoveryManifest $manifest): void
    {
        $builder->register(Paths::class, Paths::class)->setPublic(\true)->setFactory([Paths::class, 'fromPluginFile'])->addArgument($this->paths->pluginFile);
        $builder->register(Environment::class, Environment::class)->setPublic(\true)->setFactory([Environment::class, 'fromWordPress']);
        $builder->register(DiscoveryManifest::class, DiscoveryManifest::class)->setPublic(\true)->setFactory([DiscoveryManifest::class, 'fromArray'])->addArgument($manifest->toArray());
        $builder->register(EventDispatcher::class, EventDispatcher::class)->setPublic(\true);
        $builder->setAlias(EventDispatcherInterface::class, EventDispatcher::class)->setPublic(\true);
        $builder->setAlias(ContainerInterface::class, 'service_container')->setPublic(\true);
        $builder->register(NullLogger::class, NullLogger::class)->setPublic(\true);
        $builder->setAlias(LoggerInterface::class, NullLogger::class)->setPublic(\true);
        $builder->register('jooosi_mail.http_client', HttpClientInterface::class)->setPublic(\true)->setFactory([HttpClient::class, 'create']);
        $builder->setAlias(HttpClientInterface::class, 'jooosi_mail.http_client')->setPublic(\true);
        $builder->register(DatabaseConnectionFactory::class, DatabaseConnectionFactory::class)->setPublic(\true);
        $builder->register('jooosi_mail.database_connection', Connection::class)->setPublic(\true)->setFactory([new Reference(DatabaseConnectionFactory::class), 'create']);
        $builder->setAlias(Connection::class, 'jooosi_mail.database_connection')->setPublic(\true);
        $builder->register(TableNameResolver::class, TableNameResolver::class)->setPublic(\true);
        $builder->register(\JooosiMail\Infrastructure\Container\ContainerCache::class, \JooosiMail\Infrastructure\Container\ContainerCache::class)->setPublic(\true)->setAutowired(\true);
        $builder->register(SecretCipher::class, SecretCipher::class)->setPublic(\true);
        $builder->register(OptionStore::class, OptionStore::class)->setPublic(\true)->setAutowired(\true);
        $builder->register(WordPressEventPublisher::class, WordPressEventPublisher::class)->setPublic(\true);
        $builder->setAlias(EventPublisherInterface::class, WordPressEventPublisher::class)->setPublic(\true);
        $builder->setAlias(ConnectionHealthPenaltyProviderInterface::class, WebhookHealthPenaltyProvider::class)->setPublic(\true);
        $builder->register(PhpSerializer::class, PhpSerializer::class)->setPublic(\true);
        $builder->setAlias(SerializerInterface::class, PhpSerializer::class)->setPublic(\true);
        $builder->register('jooosi_mail.message_bus', MessageBusInterface::class)->setPublic(\true)->setFactory([new Reference(MessageBusFactory::class), 'create']);
        $builder->setAlias(MessageBusInterface::class, 'jooosi_mail.message_bus')->setPublic(\true);
        $builder->register(HookRegistrar::class, HookRegistrar::class)->setPublic(\true)->setAutowired(\true);
        $builder->register(RestRouteRegistrar::class, RestRouteRegistrar::class)->setPublic(\true)->setAutowired(\true);
        $builder->register(CommandRegistrar::class, CommandRegistrar::class)->setPublic(\true)->setAutowired(\true);
        $builder->register(LifecycleManager::class, LifecycleManager::class)->setPublic(\true)->setAutowired(\true);
    }
    /**
     * @since 0.1.0
     */
    private function registerDiscoveredServices(ContainerBuilder $builder, DiscoveryManifest $manifest): void
    {
        foreach ($manifest->allClasses() as $className) {
            $definition = new Definition($className);
            $definition->setAutowired(\true);
            $definition->setPublic(\true);
            $reflectionClass = new ReflectionClass($className);
            if ($reflectionClass->isAbstract()) {
                continue;
            }
            $builder->setDefinition($className, $definition);
        }
    }
}

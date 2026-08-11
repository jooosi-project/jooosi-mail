<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds Symfony mail transports, including discovered custom factories.
 *
 * @since 0.1.0
 */
#[Service]
final class TransportRegistry
{
    public function __construct(
        private readonly DiscoveryManifest $manifest,
        private readonly ContainerInterface $container,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function create(string $dsn): TransportInterface
    {
        return (new Transport($this->getFactories()))->fromString($dsn);
    }

    /**
     * @return list<TransportFactoryInterface>
     *
     * @since 0.1.0
     */
    private function getFactories(): array
    {
        $factories = iterator_to_array(Transport::getDefaultFactories($this->eventDispatcher, $this->httpClient, $this->logger));

        foreach ($this->manifest->transportFactories as $className) {
            $factory = $this->container->get($className);

            if ($factory instanceof TransportFactoryInterface) {
                $factories[] = $factory;
            }
        }

        return $factories;
    }
}

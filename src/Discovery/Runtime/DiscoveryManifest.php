<?php

declare(strict_types=1);

namespace JooosiMail\Discovery\Runtime;

/**
 * Immutable discovery output used by the container and registrars.
 *
 * @since 0.1.0
 */
final class DiscoveryManifest
{
    /**
     * @param list<class-string> $services
     * @param list<class-string> $controllers
     * @param list<class-string> $commands
     * @param list<class-string> $profiles
     * @param list<class-string> $messageHandlers
     * @param list<class-string> $transportFactories
     */
    public function __construct(
        public readonly array $services,
        public readonly array $controllers,
        public readonly array $commands,
        public readonly array $profiles,
        public readonly array $messageHandlers,
        public readonly array $transportFactories,
    ) {
    }

    /**
     * Rehydrate a manifest from dumped container data.
     *
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        return new self(
            services: $data['services'] ?? [],
            controllers: $data['controllers'] ?? [],
            commands: $data['commands'] ?? [],
            profiles: $data['profiles'] ?? [],
            messageHandlers: $data['messageHandlers'] ?? [],
            transportFactories: $data['transportFactories'] ?? [],
        );
    }

    /**
     * Export manifest data into container-safe scalars.
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return [
            'services' => $this->services,
            'controllers' => $this->controllers,
            'commands' => $this->commands,
            'profiles' => $this->profiles,
            'messageHandlers' => $this->messageHandlers,
            'transportFactories' => $this->transportFactories,
        ];
    }

    /**
     * Return all discovered class names.
     *
     * @return list<class-string>
     *
     * @since 0.1.0
     */
    public function allClasses(): array
    {
        $classes = [
            ...$this->services,
            ...$this->controllers,
            ...$this->commands,
            ...$this->profiles,
            ...$this->messageHandlers,
            ...$this->transportFactories,
        ];

        return array_values(array_unique($classes));
    }
}

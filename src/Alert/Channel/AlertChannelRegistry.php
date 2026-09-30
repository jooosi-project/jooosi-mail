<?php

declare(strict_types=1);

namespace JooosiMail\Alert\Channel;

use InvalidArgumentException;
use JooosiMail\Discovery\Attribute\Service;
use LogicException;

/**
 * Resolves alert channel drivers by their stable key.
 *
 * @since 1.0.12
 */
#[Service]
final class AlertChannelRegistry
{
    /**
     * Container tag applied to discovered alert channel drivers.
     *
     * @since 1.0.12
     */
    public const SERVICE_TAG = 'jooosi_mail.alert_channel';

    /**
     * @var array<string, AlertChannelDriverInterface>
     */
    private array $channels = [];

    /**
     * @param iterable<AlertChannelDriverInterface> $channels
     *
     * @since 1.0.12
     */
    public function __construct(iterable $channels)
    {
        foreach ($channels as $channel) {
            $key = $channel->key();

            if (preg_match('/^[a-z][a-z0-9_-]*$/D', $key) !== 1) {
                throw new LogicException(sprintf('The alert channel key "%s" is invalid.', $key));
            }

            if (isset($this->channels[$key])) {
                throw new LogicException(sprintf('The alert channel key "%s" is registered more than once.', $key));
            }

            $this->channels[$key] = $channel;
        }
    }

    /**
     * Return registered providers in container discovery order.
     *
     * @return list<AlertChannelDriverInterface>
     *
     * @since 1.0.12
     */
    public function all(): array
    {
        return array_values($this->channels);
    }

    /**
     * Resolve a channel or throw when its key is unknown.
     *
     * @since 1.0.12
     */
    public function get(string $key): AlertChannelDriverInterface
    {
        if (! isset($this->channels[$key])) {
            throw new InvalidArgumentException(sprintf('The alert channel "%s" is not supported.', $key));
        }

        return $this->channels[$key];
    }

    /**
     * Check whether a channel key is registered.
     *
     * @since 1.0.12
     */
    public function has(string $key): bool
    {
        return isset($this->channels[$key]);
    }
}

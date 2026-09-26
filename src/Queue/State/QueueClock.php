<?php

declare (strict_types=1);
namespace JooosiMail\Queue\State;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Provides UTC timestamps for queue state transitions.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueClock
{
    /**
     * @since 1.0.9
     */
    public function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
    /**
     * @since 1.0.9
     */
    public function at(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
    /**
     * @since 1.0.9
     */
    public function after(int $seconds): string
    {
        return $this->at(time() + $seconds);
    }
    /**
     * @since 1.0.9
     */
    public function before(int $seconds): string
    {
        return $this->at(time() - $seconds);
    }
}

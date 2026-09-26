<?php

declare (strict_types=1);
namespace JooosiMail\Infrastructure\Event;

/**
 * Publishes extension events without binding core services to WordPress globals.
 *
 * @since 0.1.0
 */
interface EventPublisherInterface
{
    /**
     * @since 0.1.0
     */
    public function doAction(string $hookName, mixed ...$arguments): void;
    /**
     * @since 0.1.0
     */
    public function applyFilters(string $hookName, mixed $value, mixed ...$arguments): mixed;
}

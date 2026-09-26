<?php

declare (strict_types=1);
namespace JooosiMail\Infrastructure\WordPress;

use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use function apply_filters;
use function do_action;
/**
 * Publishes Jooosi Mail events through WordPress hooks and filters.
 *
 * @since 0.1.0
 */
final class WordPressEventPublisher implements EventPublisherInterface
{
    /**
     * @since 0.1.0
     */
    public function doAction(string $hookName, mixed ...$arguments): void
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Intentional by design; all hook names are well-prefixed with `a!jooosi-mail/` or `f!jooosi-mail/`.
        do_action($hookName, ...$arguments);
    }
    /**
     * @since 0.1.0
     */
    public function applyFilters(string $hookName, mixed $value, mixed ...$arguments): mixed
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Intentional by design; all hook names are well-prefixed with `a!jooosi-mail/` or `f!jooosi-mail/`.
        return apply_filters($hookName, $value, ...$arguments);
    }
}

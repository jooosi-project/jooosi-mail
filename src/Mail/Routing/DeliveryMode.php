<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

/**
 * Supported delivery execution modes.
 *
 * @since 0.1.0
 */
enum DeliveryMode : string
{
    case Async = 'async';
    case Sync = 'sync';
    /**
     * @since 0.1.0
     */
    public static function fromMixed(mixed $value): self
    {
        return match (is_string($value) ? strtolower(trim($value)) : null) {
            self::Sync->value => self::Sync,
            default => self::Async,
        };
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Routing;

/**
 * Supported connection selection strategies.
 *
 * @since 0.1.0
 */
enum RoutingStrategy : string
{
    case Single = 'single';
    case WeightedRandom = 'weighted_random';
    case RoundRobin = 'round_robin';
    case Failover = 'failover';
    /**
     * @since 0.1.0
     */
    public static function fromMixed(mixed $value): self
    {
        return match (is_string($value) ? strtolower(trim($value)) : null) {
            self::Single->value => self::Single,
            self::WeightedRandom->value => self::WeightedRandom,
            self::RoundRobin->value => self::RoundRobin,
            self::Failover->value => self::Failover,
            default => self::WeightedRandom,
        };
    }
}

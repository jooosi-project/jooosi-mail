<?php

declare (strict_types=1);
namespace JooosiMail\Bootstrap;

/**
 * WordPress-aware environment flags.
 *
 * @since 0.1.0
 */
final class Environment
{
    public function __construct(public readonly bool $debug, public readonly string $name)
    {
    }
    /**
     * Resolve the current environment from WordPress constants.
     *
     * @since 0.1.0
     */
    public static function fromWordPress(): self
    {
        $debug = defined('WP_DEBUG') && \WP_DEBUG;
        return new self(debug: $debug, name: $debug ? 'development' : 'production');
    }
}

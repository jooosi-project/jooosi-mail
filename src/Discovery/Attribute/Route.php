<?php

declare(strict_types=1);

namespace JooosiMail\Discovery\Attribute;

use Attribute;

/**
 * Declares a REST route on a controller method.
 *
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @param list<string>|string $methods
     * @param array<string, mixed> $args
     * @param array{0: class-string|object, 1: string}|string $permissionCallback Global function name,
     *                                                                        static callable, or controller/service method reference.
     */
    public function __construct(
        public readonly string $path,
        public readonly array|string $methods = 'GET',
        public readonly array|string $permissionCallback = '__return_false',
        public readonly array $args = [],
    ) {
    }
}

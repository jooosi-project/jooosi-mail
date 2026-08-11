<?php

declare(strict_types=1);

namespace JooosiMail\Discovery\Attribute;

use Attribute;

/**
 * Marks a class as a REST controller.
 *
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Controller
{
    public function __construct(
        public readonly string $namespace = 'jooosi-mail/v1',
        public readonly string $prefix = '',
    ) {
    }
}

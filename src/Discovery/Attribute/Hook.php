<?php

declare (strict_types=1);
namespace JooosiMail\Discovery\Attribute;

use Attribute;
/**
 * Declares a WordPress hook callback on a service method.
 *
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Hook
{
    public function __construct(public readonly string $name, public readonly string $kind = 'auto', public readonly int $priority = 10, public readonly int $acceptedArgs = 1)
    {
    }
}

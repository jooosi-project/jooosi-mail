<?php

declare (strict_types=1);
namespace JooosiMail\Discovery\Attribute;

use Attribute;
/**
 * Marks a class for container registration.
 *
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Service
{
    public function __construct(public readonly bool $public = \true)
    {
    }
}

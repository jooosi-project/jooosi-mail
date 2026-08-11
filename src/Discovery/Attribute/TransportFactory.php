<?php

declare(strict_types=1);

namespace JooosiMail\Discovery\Attribute;

use Attribute;

/**
 * Marks a custom Symfony mail transport factory.
 *
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class TransportFactory
{
}

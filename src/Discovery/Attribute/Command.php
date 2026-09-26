<?php

declare (strict_types=1);
namespace JooosiMail\Discovery\Attribute;

use Attribute;
/**
 * Marks a service class or public method as a WP-CLI command.
 *
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Command
{
    /**
     * @param list<string> $aliases
     *
     * @since 0.1.0
     */
    public function __construct(public readonly ?string $name = null, public readonly string $description = '', public readonly array $aliases = [], public readonly ?string $synopsis = null, public readonly ?string $when = 'after_wp_load')
    {
    }
}

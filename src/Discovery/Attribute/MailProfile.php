<?php

declare (strict_types=1);
namespace JooosiMail\Discovery\Attribute;

use Attribute;
/**
 * Marks a class as a discovered mail profile.
 *
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class MailProfile
{
    /**
     * @param list<string>                            $useCases
     * @param array<string, scalar|list<scalar>|null> $extra
     */
    public function __construct(public readonly string $key, public readonly ?string $label = null, public readonly ?string $description = null, public readonly ?string $website = null, public readonly ?string $docsUrl = null, public readonly array $useCases = [], public readonly array $extra = [])
    {
    }
}

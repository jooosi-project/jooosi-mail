<?php

declare (strict_types=1);
namespace JooosiMail\Cli\Application;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Normalizes boolean values received from WP-CLI options.
 *
 * WP-CLI may provide an option as a native boolean, a numeric string, or a
 * textual value. Keeping the normalization in one place prevents a value such
 * as `false` from being treated as truthy by a direct PHP cast.
 *
 * @since 1.0.9
 */
#[Service]
final class CliBooleanParser
{
    /**
     * Parse a WP-CLI option value.
     *
     * @since 1.0.9
     */
    public function parse(mixed $value, bool $default = \false): bool
    {
        $resolved = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        return is_bool($resolved) ? $resolved : $default;
    }
}

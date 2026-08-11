<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\Database;

/**
 * Resolves plugin table names using the active WordPress prefix.
 *
 * @since 0.1.0
 */
final class TableNameResolver
{
    /**
     * Resolve the full table name for a Jooosi Mail table suffix.
     *
     * @since 0.1.0
     */
    public function resolve(string $suffix): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jooosi_mail_' . $suffix;
    }
}

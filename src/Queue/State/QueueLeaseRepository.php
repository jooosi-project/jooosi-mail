<?php

declare(strict_types=1);

namespace JooosiMail\Queue\State;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Persists owner-token queue leases in the WordPress options table.
 *
 * The conditional delete intentionally uses WordPress's database connection so
 * option reads, writes, and tests share one transaction boundary.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueLeaseRepository
{
    /**
     * @since 1.0.9
     */
    public function add(QueueLease $lease): bool
    {
        return add_option($lease->optionName, [
            'expires_at' => $lease->expiresAt,
            'owner_token' => $lease->ownerToken,
        ], '', false);
    }

    /**
     * @return array{lease: QueueLease|null, serialized_value: string}|null
     *
     * @since 1.0.9
     */
    public function find(string $optionName): ?array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is provided by WordPress; the value is parameterized.
        $serializedValue = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            $optionName,
        ));

        if (! is_string($serializedValue)) {
            return null;
        }

        $value = maybe_unserialize($serializedValue);
        $lease = null;

        if (is_array($value)) {
            $expiresAt = $value['expires_at'] ?? null;
            $ownerToken = $value['owner_token'] ?? null;

            if (is_numeric($expiresAt) && is_string($ownerToken) && $ownerToken !== '') {
                $lease = new QueueLease(
                    optionName: $optionName,
                    ownerToken: $ownerToken,
                    expiresAt: (int) $expiresAt,
                );
            }
        }

        if ($lease === null && (is_int($value) || (is_string($value) && is_numeric($value)))) {
            $lease = new QueueLease(
                optionName: $optionName,
                ownerToken: '',
                expiresAt: (int) $value,
            );
        }

        return [
            'lease' => $lease,
            'serialized_value' => $serializedValue,
        ];
    }

    /**
     * Deletes an option only when its stored value has not changed since read.
     *
     * @since 1.0.9
     */
    public function deleteIfMatches(string $optionName, string $serializedValue): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is provided by WordPress; both values are parameterized.
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            $optionName,
            $serializedValue,
        )) === 1;

        if ($deleted) {
            wp_cache_delete($optionName, 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
        }

        return $deleted;
    }
}

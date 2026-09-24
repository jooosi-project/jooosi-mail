<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Normalizes primitive connection configuration input.
 *
 * @since 1.0.9
 */
#[Service]
final class ConnectionInputNormalizer
{
    /**
     * @param array<string, mixed> $input
     *
     * @since 1.0.9
     */
    public function resolveBoolean(array $input, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $input)) {
            return $default;
        }

        $value = $input[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (! is_scalar($value)) {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 1.0.9
     */
    public function resolveInt(array $input, string $key, int $default, int $minimum = 0): int
    {
        if (! array_key_exists($key, $input)) {
            return $default;
        }

        if (! is_scalar($input[$key])) {
            return $minimum;
        }

        return max($minimum, (int) $input[$key]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 1.0.9
     */
    public function extractPositiveIntOrZero(array $input, string $key): ?int
    {
        if (! array_key_exists($key, $input)) {
            return null;
        }

        if (! is_scalar($input[$key])) {
            return 0;
        }

        return max(0, (int) $input[$key]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 1.0.9
     */
    public function extractScalarString(array $input, string $key): ?string
    {
        if (! array_key_exists($key, $input)) {
            return null;
        }

        $value = $input[$key];

        if (! is_scalar($value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $field
     *
     * @since 1.0.9
     */
    public function isSecretField(array $field): bool
    {
        return (($field['type'] ?? null) === 'password') || (($field['secret'] ?? false) === true);
    }

    /**
     * @param array<string, mixed> $field
     *
     * @since 1.0.9
     */
    public function normalizeConfigurationValue(array $field, mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return match ($field['type'] ?? null) {
            'number' => (int) $normalized,
            default => $normalized,
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     *
     * @since 1.0.9
     */
    public function decodeJsonArray(array $input, string $key): ?array
    {
        $value = $this->extractScalarString($input, $key);

        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Option "%s" must be valid JSON object data.', $key));
        }

        return $decoded;
    }
}

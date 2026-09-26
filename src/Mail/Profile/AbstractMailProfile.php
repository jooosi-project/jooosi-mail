<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile;

use JooosiMail\Discovery\Attribute\MailProfile;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionConfigurationException;
use Override;
use ReflectionClass;
use RuntimeException;
/**
 * Shared defaults for mail profiles.
 *
 * @since 0.1.0
 */
abstract class AbstractMailProfile implements \JooosiMail\Mail\Profile\MailProfileInterface
{
    /**
     * @since 0.1.0
     */
    final protected function profileKey(): string
    {
        return $this->profileDefinition()->key;
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function supportsWebhooks(): bool
    {
        return \false;
    }
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getWebhookEvents(): array
    {
        return [];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    #[Override]
    public function getConfigurationDefaults(?Connection $existingConnection = null): array
    {
        $defaults = [];
        foreach ($this->getConfigurationFields() as $name => $field) {
            $storedValue = $this->getStoredConfigurationValue($existingConnection, $name, $this->isSecretField($field));
            if ($storedValue !== null) {
                $defaults[$name] = $storedValue;
                continue;
            }
            if (array_key_exists('default', $field)) {
                $defaults[$name] = $field['default'];
            }
        }
        return $defaults;
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function validateConfiguration(Connection $connection): void
    {
    }
    /**
     * @since 0.1.0
     */
    protected function extractScalarString(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || !is_scalar($input[$key])) {
            return null;
        }
        return trim((string) $input[$key]);
    }
    /**
     * @since 0.1.0
     */
    protected function extractPositiveIntOrZero(array $input, string $key): ?int
    {
        if (!array_key_exists($key, $input)) {
            return null;
        }
        return max(0, (int) $input[$key]);
    }
    /**
     * @since 0.1.0
     */
    protected function hasConfigurationValue(mixed $value): bool
    {
        if ($value === null) {
            return \false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }
        return \true;
    }
    /**
     * @param array<string, mixed> $defaults
     * @param list<string>         $fieldNames
     *
     * @since 0.1.0
     */
    protected function assertRequiredConfigurationValues(array $defaults, string $profileKey, string $scheme, array $fieldNames): void
    {
        foreach ($fieldNames as $fieldName) {
            $value = $defaults[$fieldName] ?? null;
            if ($this->hasConfigurationValue($value)) {
                continue;
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Configuration field "%s" is required for profile "%s" when using scheme "%s".', $fieldName, $profileKey, $scheme));
        }
    }
    /**
     * @param list<string>|string $values
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    protected function conditionIn(string $field, array|string $values): array
    {
        return ['field' => $field, 'operator' => 'in', 'values' => $this->normalizeConditionValues($values)];
    }
    /**
     * @param list<string>|string $values
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    protected function conditionNotIn(string $field, array|string $values): array
    {
        return ['field' => $field, 'operator' => 'not_in', 'values' => $this->normalizeConditionValues($values)];
    }
    /**
     * @param array<string, scalar|null> $parameters
     *
     * @since 0.1.0
     */
    protected function buildQueryString(array $parameters): string
    {
        $normalized = [];
        foreach ($parameters as $key => $value) {
            if (!$this->hasConfigurationValue($value)) {
                continue;
            }
            $normalized[$key] = (string) $value;
        }
        if ($normalized === []) {
            return '';
        }
        return http_build_query($normalized, '', '&', \PHP_QUERY_RFC3986);
    }
    /**
     * @param array<string, mixed> $field
     *
     * @since 0.1.0
     */
    protected function isSecretField(array $field): bool
    {
        return ($field['type'] ?? null) === 'password' || ($field['secret'] ?? \false) === \true;
    }
    /**
     * @param list<string>|string $values
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function normalizeConditionValues(array|string $values): array
    {
        return array_values(array_map('strval', is_array($values) ? $values : [$values]));
    }
    /**
     * @since 0.1.0
     */
    private function profileDefinition(): MailProfile
    {
        $attributes = (new ReflectionClass($this))->getAttributes(MailProfile::class);
        if ($attributes === []) {
            throw new RuntimeException(sprintf('Profile "%s" is missing the #[MailProfile] attribute.', static::class));
        }
        return $attributes[0]->newInstance();
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    protected function getProfileSettings(?Connection $existingConnection): array
    {
        return $existingConnection?->getProfileSettings() ?? [];
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    protected function getProfileSecrets(?Connection $existingConnection): array
    {
        return $existingConnection?->getProfileSecrets() ?? [];
    }
    /**
     * @since 0.1.0
     */
    private function getStoredConfigurationValue(?Connection $existingConnection, string $name, bool $secret): mixed
    {
        if (!$existingConnection instanceof Connection) {
            return null;
        }
        $profileData = $secret ? $this->getProfileSecrets($existingConnection) : $this->getProfileSettings($existingConnection);
        if (array_key_exists($name, $profileData)) {
            return $profileData[$name];
        }
        return null;
    }
}

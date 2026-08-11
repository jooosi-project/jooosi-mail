<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;

/**
 * Validates resolved connection configuration against profile metadata.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionConfigurationValidator
{
    public function __construct(
        private readonly ProfileMetadataResolver $profileMetadataResolver,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function validate(MailProfileInterface $profile, Connection $connection): void
    {
        if ($connection->dsn !== null && $connection->dsn !== '') {
            $this->validateDsnScheme($profile, $connection->dsn);

            return;
        }

        $defaults = $profile->getConfigurationDefaults($connection);

        foreach ($profile->getConfigurationFields() as $fieldName => $field) {
            if (! $this->isFieldVisible($field, $defaults)) {
                continue;
            }

            $value = $defaults[$fieldName] ?? null;

            if ($this->isFieldRequired($field, $defaults) && ! $this->hasConfigurationValue($value)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new ConnectionConfigurationException($this->buildRequiredFieldMessage($fieldName, $field, $profile, $defaults));
            }

            $choices = is_array($field['choices'] ?? null) ? $field['choices'] : [];

            if ($choices !== [] && $this->hasConfigurationValue($value) && ! in_array((string) $value, array_map('strval', $choices), true)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new ConnectionConfigurationException(sprintf('Configuration field "%s" must be one of: %s.', $fieldName, implode(', ', array_map('strval', $choices))));
            }
        }

        $profile->validateConfiguration($connection);
    }

    /**
     * @since 0.1.0
     */
    private function validateDsnScheme(MailProfileInterface $profile, string $dsn): void
    {
        $scheme = $this->extractDsnScheme($dsn);

        if ($scheme === null) {
            throw new ConnectionConfigurationException('The DSN scheme could not be detected.');
        }

        if (! in_array($scheme, $profile->getSupportedSchemes(), true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Profile "%s" does not support DSN scheme "%s".', $this->profileMetadataResolver->getKey($profile), $scheme));
        }
    }

    /**
     * @since 0.1.0
     */
    private function extractDsnScheme(string $dsn): ?string
    {
        if (preg_match('/^([a-z0-9+._-]+):\/\//i', $dsn, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /**
     * @since 0.1.0
     */
    private function hasConfigurationValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function isFieldVisible(array $field, array $values): bool
    {
        $conditions = $this->normalizeFieldConditions($field['visible_when'] ?? null);

        if ($conditions === []) {
            return true;
        }

        return $this->matchesFieldConditions($conditions, $values);
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function isFieldRequired(array $field, array $values): bool
    {
        if (($field['required'] ?? false) === true) {
            return true;
        }

        $conditions = $this->normalizeFieldConditions($field['required_when'] ?? null);

        if ($conditions === []) {
            return false;
        }

        return $this->matchesFieldConditions($conditions, $values);
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function buildRequiredFieldMessage(string $fieldName, array $field, MailProfileInterface $profile, array $values): string
    {
        $customMessage = $field['required_message'] ?? null;

        if (is_string($customMessage) && $customMessage !== '') {
            return $customMessage;
        }

        $conditions = $this->normalizeFieldConditions($field['required_when'] ?? null);

        if (count($conditions) === 1 && ($conditions[0]['field'] ?? null) === 'scheme' && ($conditions[0]['operator'] ?? null) === 'in') {
            $scheme = $this->extractConditionStringValue($values, 'scheme');

            if ($scheme !== '') {
                return sprintf('Configuration field "%s" is required for profile "%s" when using scheme "%s".', $fieldName, $this->profileMetadataResolver->getKey($profile), $scheme);
            }
        }

        return sprintf('Configuration field "%s" is required for profile "%s".', $fieldName, $this->profileMetadataResolver->getKey($profile));
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @param array<string, mixed>             $values
     *
     * @since 0.1.0
     */
    private function matchesFieldConditions(array $conditions, array $values): bool
    {
        foreach ($conditions as $condition) {
            $fieldName = (string) ($condition['field'] ?? '');

            if ($fieldName === '') {
                continue;
            }

            $expectedValues = array_map(
                fn (string $value): string => $this->normalizeFieldConditionValue($value),
                array_values(array_map('strval', is_array($condition['values'] ?? null) ? $condition['values'] : [])),
            );
            $actualValue = $this->normalizeFieldConditionValue($this->extractConditionStringValue($values, $fieldName));

            if (($condition['operator'] ?? 'in') === 'not_in') {
                if (in_array($actualValue, $expectedValues, true)) {
                    return false;
                }

                continue;
            }

            if (! in_array($actualValue, $expectedValues, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeFieldConditions(mixed $conditionSet): array
    {
        if (! is_array($conditionSet)) {
            return [];
        }

        $normalized = [];

        foreach ($conditionSet as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $fieldName = isset($condition['field']) ? trim((string) $condition['field']) : '';

            if ($fieldName === '') {
                continue;
            }

            $operator = strtolower(trim((string) ($condition['operator'] ?? 'in')));

            if (! in_array($operator, ['in', 'not_in'], true)) {
                continue;
            }

            $normalized[] = [
                'field' => $fieldName,
                'operator' => $operator,
                'values' => array_values(array_map('strval', is_array($condition['values'] ?? null) ? $condition['values'] : [])),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function extractConditionStringValue(array $values, string $fieldName): string
    {
        $value = $values[$fieldName] ?? null;

        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @since 0.1.0
     */
    private function normalizeFieldConditionValue(string $value): string
    {
        return strtolower(trim($value));
    }
}

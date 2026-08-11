<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;
use JooosiMail\Mail\Profile\ProfileRegistry;

/**
 * Builds admin-safe connection payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionViewFactory
{
    public function __construct(
        private readonly ProfileRegistry $profileRegistry,
        private readonly ProfileMetadataResolver $profileMetadataResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function createListItem(Connection $connection): array
    {
        $profile = $this->resolveProfile($connection);

        return [
            'id' => $connection->id,
            'profile' => $this->createProfilePayload($profile),
            'name' => $connection->name,
            'enabled' => $connection->enabled,
            'default' => $connection->default,
            'priority' => $connection->priority,
            'weight' => $connection->weight,
            'webhookEnabled' => $connection->webhookEnabled,
            'webhookSecretConfigured' => $connection->hasWebhookSecret(),
            'dsnOverrideConfigured' => $connection->hasDsnOverride(),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function createDetail(Connection $connection): array
    {
        $profile = $this->resolveProfile($connection);

        return array_replace($this->createListItem($connection), [
            'profile' => $this->createProfilePayload($profile, true),
            'configurationFields' => $this->createConfigurationFields($profile, $connection),
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function createProfilePayload(MailProfileInterface $profile, bool $includeDescription = false): array
    {
        $payload = [
            'key' => $this->profileMetadataResolver->getKey($profile),
            'label' => $this->profileMetadataResolver->getLabel($profile),
            'supportsWebhooks' => $profile->supportsWebhooks(),
        ];

        if ($includeDescription) {
            $payload['description'] = $this->profileMetadataResolver->getDescription($profile);
        }

        $metadata = $this->profileMetadataResolver->resolve($profile);

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        return $payload;
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function createConfigurationFields(MailProfileInterface $profile, Connection $connection): array
    {
        $defaults = $profile->getConfigurationDefaults($connection);
        $profileSecrets = $connection->getProfileSecrets();
        $fields = [];

        foreach ($profile->getConfigurationFields() as $name => $field) {
            $fieldView = [
                'name' => $name,
                'label' => (string) ($field['label'] ?? $name),
                'type' => (string) ($field['type'] ?? 'text'),
                'required' => (bool) ($field['required'] ?? false),
            ];

            $choices = $field['choices'] ?? null;

            if (is_array($choices) && $choices !== []) {
                $fieldView['choices'] = array_values(array_map('strval', $choices));
            }

            $visibleWhen = $this->normalizeFieldConditions($field['visible_when'] ?? null);

            if ($visibleWhen !== []) {
                $fieldView['visibleWhen'] = $visibleWhen;
            }

            $requiredWhen = $this->normalizeFieldConditions($field['required_when'] ?? null);

            if ($requiredWhen !== []) {
                $fieldView['requiredWhen'] = $requiredWhen;
            }

            if ($this->isSecretField($field)) {
                $fieldView['secret'] = true;
                $fieldView['configured'] = $this->hasConfiguredValue($profileSecrets[$name] ?? $defaults[$name] ?? null);
            } else {
                $fieldView['secret'] = false;
                $fieldView['value'] = $defaults[$name] ?? null;
            }

            $fields[$name] = $fieldView;
        }

        return $fields;
    }

    /**
     * @since 0.1.0
     */
    private function resolveProfile(Connection $connection): MailProfileInterface
    {
        $profile = $this->profileRegistry->get($connection->profileKey);

        if (! $profile instanceof MailProfileInterface) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('Profile "%s" is not registered.', $connection->profileKey));
        }

        return $profile;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @since 0.1.0
     */
    private function isSecretField(array $field): bool
    {
        return (($field['type'] ?? null) === 'password') || (($field['secret'] ?? false) === true);
    }

    /**
     * @since 0.1.0
     */
    private function hasConfiguredValue(mixed $value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        return trim((string) $value) !== '';
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
}

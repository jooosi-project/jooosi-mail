<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionViewFactory;
use Throwable;

/**
 * Builds admin-facing connection payloads without exposing secrets.
 *
 * @since 0.1.0
 */
#[Service]
final class AdminConnectionPayloadFactory
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly ConnectionViewFactory $connectionViewFactory,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function createList(Connection $connection): array
    {
        try {
            $payload = $this->connectionViewFactory->createListItem($connection);
        } catch (Throwable $throwable) {
            $payload = $this->createFallbackPayload($connection, false, $throwable->getMessage());
        }

        $payload['profile']['missing'] = (bool) ($payload['profile']['missing'] ?? false);
        $payload['profile']['supportsWebhooks'] = (bool) ($payload['profile']['supportsWebhooks'] ?? false);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function createDetail(Connection $connection): array
    {
        try {
            $payload = $this->connectionViewFactory->createDetail($connection);
            $payload['configurationFields'] = $this->normalizeConfigurationFields(
                is_array($payload['configurationFields'] ?? null) ? $payload['configurationFields'] : [],
            );
        } catch (Throwable $throwable) {
            $payload = $this->createFallbackPayload($connection, true, $throwable->getMessage());
        }

        $payload['profile']['missing'] = (bool) ($payload['profile']['missing'] ?? false);
        $payload['profile']['supportsWebhooks'] = (bool) ($payload['profile']['supportsWebhooks'] ?? false);

        return $payload;
    }

    /**
     * @param array<string, array<string, mixed>> $configurationFields
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeConfigurationFields(array $configurationFields): array
    {
        $normalizedFields = [];

        foreach ($configurationFields as $fieldName => $field) {
            $normalizedFields[] = [
                'name' => (string) ($field['name'] ?? $fieldName),
                'label' => (string) ($field['label'] ?? $fieldName),
                'type' => (string) ($field['type'] ?? 'text'),
                'required' => (bool) ($field['required'] ?? false),
                'secret' => (bool) ($field['secret'] ?? false),
                'configured' => (bool) ($field['configured'] ?? false),
                'value' => $field['value'] ?? null,
                'choices' => array_values(array_map('strval', is_array($field['choices'] ?? null) ? $field['choices'] : [])),
                'visibleWhen' => $this->normalizeFieldConditions($field['visibleWhen'] ?? null),
                'requiredWhen' => $this->normalizeFieldConditions($field['requiredWhen'] ?? null),
            ];
        }

        return $normalizedFields;
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
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function createFallbackPayload(Connection $connection, bool $includeDetail, string $reason): array
    {
        $payload = [
            'id' => $connection->id,
            'profile' => [
                'key' => $connection->profileKey,
                'label' => $connection->profileKey,
                'missing' => true,
                'supportsWebhooks' => false,
            ],
            'name' => $connection->name,
            'enabled' => $connection->enabled,
            'default' => $connection->default,
            'priority' => $connection->priority,
            'weight' => $connection->weight,
            'webhookEnabled' => $connection->webhookEnabled,
            'webhookSecretConfigured' => $connection->hasWebhookSecret(),
            'dsnOverrideConfigured' => $connection->hasDsnOverride(),
        ];

        if (! $includeDetail) {
            return $payload;
        }

        $payload['profile']['description'] = $reason;
        $payload['profile']['supportsWebhooks'] = false;
        $payload['configurationFields'] = [];

        return $payload;
    }
}

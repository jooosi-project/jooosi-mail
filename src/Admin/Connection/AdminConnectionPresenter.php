<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
/**
 * Builds the complete public REST representation for admin connections.
 *
 * @since 1.0.9
 */
#[Service]
final class AdminConnectionPresenter
{
    public function __construct(private readonly \JooosiMail\Admin\Connection\AdminConnectionPayloadFactory $payloadFactory)
    {
    }
    /**
     * @param list<array<string, mixed>> $profiles
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function profiles(array $profiles): array
    {
        usort($profiles, static fn(array $left, array $right): int => strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? '')));
        return array_map(function (array $profile): array {
            $configurationFields = [];
            foreach ((array) ($profile['configuration_fields'] ?? []) as $fieldName => $field) {
                if (!is_array($field)) {
                    continue;
                }
                $configurationFields[] = ['name' => (string) $fieldName, 'label' => (string) ($field['label'] ?? $fieldName), 'type' => (string) ($field['type'] ?? 'text'), 'required' => (bool) ($field['required'] ?? \false), 'description' => is_string($field['description'] ?? null) ? trim($field['description']) : '', 'secret' => ($field['type'] ?? null) === 'password' || ($field['secret'] ?? \false) === \true, 'default' => $field['default'] ?? null, 'choices' => array_values(array_map('strval', is_array($field['choices'] ?? null) ? $field['choices'] : [])), 'visibleWhen' => $this->normalizeFieldConditions($field['visible_when'] ?? null), 'requiredWhen' => $this->normalizeFieldConditions($field['required_when'] ?? null)];
            }
            $payload = ['key' => (string) ($profile['key'] ?? ''), 'label' => (string) ($profile['label'] ?? ''), 'description' => (string) ($profile['description'] ?? ''), 'schemes' => array_values(array_map('strval', is_array($profile['schemes'] ?? null) ? $profile['schemes'] : [])), 'supportsWebhooks' => (bool) ($profile['supports_webhooks'] ?? \false), 'configurationFields' => $configurationFields];
            if (is_array($profile['metadata'] ?? null) && $profile['metadata'] !== []) {
                $payload['metadata'] = $profile['metadata'];
            }
            return $payload;
        }, $profiles);
    }
    /**
     * @param array<string, mixed>|null $status
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function listItem(Connection $connection, ?array $status): array
    {
        return $this->withStatusPayload($this->baseList($connection), $connection, $status);
    }
    /**
     * Creates the shared secret-safe list projection before endpoint-specific status fields.
     *
     * @since 1.0.9
     */
    public function baseList(Connection $connection): array
    {
        return $this->payloadFactory->createList($connection);
    }
    /**
     * @param array<string, mixed>|null $status
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function detail(Connection $connection, ?array $status): array
    {
        $payload = $this->payloadFactory->createDetail($connection);
        $payload['dsn'] = $connection->dsn;
        $payload['rateLimits'] = ['minute' => $this->extractConnectionSetting($connection, ['rate_limits', 'minute'], 'rate_limit_per_minute'), 'hour' => $this->extractConnectionSetting($connection, ['rate_limits', 'hour'], 'rate_limit_per_hour'), 'day' => $this->extractConnectionSetting($connection, ['rate_limits', 'day'], 'rate_limit_per_day')];
        $payload['circuitBreaker'] = ['threshold' => $this->extractConnectionSetting($connection, ['circuit_breaker', 'threshold'], 'circuit_breaker_threshold'), 'window' => $this->extractConnectionSetting($connection, ['circuit_breaker', 'window'], 'circuit_breaker_window'), 'cooldown' => $this->extractConnectionSetting($connection, ['circuit_breaker', 'cooldown'], 'circuit_breaker_cooldown')];
        $payload['sender'] = $this->extractSenderSettings($connection);
        return $this->withStatusPayload($payload, $connection, $status);
    }
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $status
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    private function withStatusPayload(array $payload, Connection $connection, ?array $status): array
    {
        $availability = is_array($status['availability'] ?? null) ? $status['availability'] : [];
        $circuitBreaker = is_array($availability['circuit_breaker'] ?? null) ? $availability['circuit_breaker'] : [];
        $rateLimit = is_array($availability['rate_limit'] ?? null) ? $availability['rate_limit'] : [];
        $payload['healthScore'] = (int) ($status['health_score'] ?? 0);
        $payload['available'] = (bool) ($availability['available'] ?? $connection->enabled);
        $payload['unavailableReasons'] = array_values(array_map('strval', is_array($availability['unavailable_reasons'] ?? null) ? $availability['unavailable_reasons'] : []));
        $payload['nextAvailableAt'] = $this->normalizeDateTime($availability['next_available_at'] ?? null);
        $payload['webhookUrl'] = $connection->id !== null ? rest_url('jooosi-mail/v1/webhook/' . $connection->id) : null;
        $payload['rateLimitStatus'] = ['blocked' => (bool) ($rateLimit['blocked'] ?? \false), 'windows' => is_array($rateLimit['windows'] ?? null) ? $rateLimit['windows'] : []];
        $payload['circuitBreakerStatus'] = ['enabled' => (bool) ($circuitBreaker['enabled'] ?? \false), 'recentFailures' => (int) ($circuitBreaker['recent_failures'] ?? 0), 'blacklistedUntil' => $this->normalizeDateTime($circuitBreaker['blacklisted_until'] ?? null)];
        return $payload;
    }
    /**
     * @param mixed $conditionSet
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    private function normalizeFieldConditions(mixed $conditionSet): array
    {
        if (!is_array($conditionSet)) {
            return [];
        }
        $normalized = [];
        foreach ($conditionSet as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            $fieldName = isset($condition['field']) ? trim((string) $condition['field']) : '';
            if ($fieldName === '') {
                continue;
            }
            $operator = strtolower(trim((string) ($condition['operator'] ?? 'in')));
            if (!in_array($operator, ['in', 'not_in'], \true)) {
                continue;
            }
            $normalized[] = ['field' => $fieldName, 'operator' => $operator, 'values' => array_values(array_map('strval', is_array($condition['values'] ?? null) ? $condition['values'] : []))];
        }
        return $normalized;
    }
    /**
     * @param list<string> $path
     *
     * @since 1.0.9
     */
    private function extractConnectionSetting(Connection $connection, array $path, string $legacyKey): int|string|null
    {
        $value = $connection->settings;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $value = $connection->settings[$legacyKey] ?? null;
                break;
            }
            $value = $value[$segment];
        }
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (int) $value : (string) $value;
    }
    /**
     * @return array{email: string, name: string, forceEmail: bool, forceName: bool, returnPathMode: string, returnPathEmail: string}
     *
     * @since 1.0.9
     */
    private function extractSenderSettings(Connection $connection): array
    {
        $sender = is_array($connection->settings['sender'] ?? null) ? $connection->settings['sender'] : [];
        return ['email' => $this->extractSenderString($sender['email'] ?? null), 'name' => $this->extractSenderString($sender['name'] ?? null), 'forceEmail' => (bool) ($sender['force_email'] ?? \false), 'forceName' => (bool) ($sender['force_name'] ?? \false), 'returnPathMode' => $this->extractSenderString($sender['return_path_mode'] ?? null, 'inherit'), 'returnPathEmail' => $this->extractSenderString($sender['return_path_email'] ?? null)];
    }
    /**
     * @since 1.0.9
     */
    private function extractSenderString(mixed $value, string $default = ''): string
    {
        if (!is_scalar($value)) {
            return $default;
        }
        $value = trim((string) $value);
        return $value !== '' ? $value : $default;
    }
    /**
     * @since 1.0.9
     */
    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return gmdate(\DATE_ATOM, (int) $value);
        }
        return is_string($value) ? $value : null;
    }
}

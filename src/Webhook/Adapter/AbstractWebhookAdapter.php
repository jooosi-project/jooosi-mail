<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Base webhook adapter with generic parsing helpers.
 *
 * @since 0.1.0
 */
abstract class AbstractWebhookAdapter implements \JooosiMail\Webhook\Adapter\WebhookAdapterInterface
{
    #[Override]
    public function getPriority(): int
    {
        return 0;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return \false;
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        return \false;
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return 'unsupported';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        return [['event_type' => (string) ($request->get_param('event') ?: 'received'), 'transport_message_id' => (string) ($request->get_param('message_id') ?: ''), 'provider_event_id' => (string) ($request->get_param('event_id') ?: ''), 'payload' => ['headers' => $request->get_headers(), 'body' => $request->get_body(), 'params' => $request->get_params()], 'occurred_at' => gmdate('Y-m-d H:i:s')]];
    }
    /**
     * @return array<string, mixed>|list<mixed>
     *
     * @since 0.1.0
     */
    protected function decodeJsonBody(WP_REST_Request $request): array
    {
        $decoded = json_decode($request->get_body(), \true);
        return is_array($decoded) ? $decoded : [];
    }
    /**
     * @since 0.1.0
     */
    protected function normalizeEventType(string $eventType): string
    {
        $eventType = strtolower(trim($eventType));
        $eventType = preg_replace('/[^a-z0-9]+/', '_', $eventType) ?? 'received';
        return trim($eventType, '_') !== '' ? trim($eventType, '_') : 'received';
    }
    /**
     * @since 0.1.0
     */
    protected function formatOccurredAt(mixed $value): string
    {
        if (is_numeric($value)) {
            return gmdate('Y-m-d H:i:s', (int) $value);
        }
        if (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);
            if ($timestamp !== \false) {
                return gmdate('Y-m-d H:i:s', $timestamp);
            }
        }
        return gmdate('Y-m-d H:i:s');
    }
    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     *
     * @since 0.1.0
     */
    protected function extractFirstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }
        return null;
    }
    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     *
     * @since 0.1.0
     */
    protected function extractFirstInt(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value) && (int) $value > 0) {
                return (int) $value;
            }
        }
        return null;
    }
    /**
     * @param array<string, mixed> $payload
     * @param list<string> $path
     *
     * @since 0.1.0
     */
    protected function extractNestedValue(array $payload, array $path): mixed
    {
        $current = $payload;
        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
    }
    /**
     * @param array<string, mixed> $payload
     *
     * @since 0.1.0
     */
    protected function extractMailLogId(array $payload): ?int
    {
        $direct = $this->extractFirstInt($payload, ['mail_log_id', 'jooosi_mail_mail_log_id']);
        if ($direct !== null) {
            return $direct;
        }
        $nested = [['custom_args', 'mail_log_id'], ['custom_args', 'jooosi_mail_mail_log_id'], ['custom_variables', 'mail_log_id'], ['custom_variables', 'jooosi_mail_mail_log_id'], ['unique_args', 'mail_log_id'], ['unique_args', 'jooosi_mail_mail_log_id'], ['metadata', 'mail_log_id'], ['metadata', 'jooosi_mail_mail_log_id'], ['data', 'mail_log_id'], ['data', 'jooosi_mail_mail_log_id'], ['msg', 'metadata', 'mail_log_id'], ['msg', 'metadata', 'jooosi_mail_mail_log_id']];
        foreach ($nested as $path) {
            $value = $this->extractNestedValue($payload, $path);
            if (is_scalar($value) && (int) $value > 0) {
                return (int) $value;
            }
        }
        return null;
    }
    /**
     * @param list<string> $ranges
     *
     * @since 0.1.0
     */
    protected function requestMatchesIpRanges(array $ranges): bool
    {
        $remoteAddress = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddress === '') {
            return \false;
        }
        foreach ($ranges as $range) {
            if ($this->ipMatchesRange($remoteAddress, $range)) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * @since 0.1.0
     */
    private function ipMatchesRange(string $remoteAddress, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return strcasecmp($remoteAddress, $range) === 0;
        }
        [$subnet, $prefixLength] = explode('/', $range, 2);
        if (!filter_var($remoteAddress, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) || !filter_var($subnet, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return \false;
        }
        $prefix = (int) $prefixLength;
        if ($prefix < 0 || $prefix > 32) {
            return \false;
        }
        $remote = ip2long($remoteAddress);
        $network = ip2long($subnet);
        if ($remote === \false || $network === \false) {
            return \false;
        }
        $mask = $prefix === 0 ? 0 : -1 << 32 - $prefix;
        return ($remote & $mask) === ($network & $mask);
    }
}

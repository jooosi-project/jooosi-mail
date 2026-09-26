<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Presentation\Log;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Projects webhook event rows into the public admin API representation.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookEventPresenter
{
    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function presentMany(array $events, bool $includePayloadJson = \true): array
    {
        return array_map(fn(array $event): array => $this->present($event, $includePayloadJson), $events);
    }
    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function present(array $event, bool $includePayloadJson = \true): array
    {
        $payload = ['id' => (int) ($event['id'] ?? 0), 'connectionId' => isset($event['connection_id']) ? (int) $event['connection_id'] : null, 'connectionName' => isset($event['connection_name']) ? (string) $event['connection_name'] : null, 'connectionProfileKey' => isset($event['connection_profile_key']) ? (string) $event['connection_profile_key'] : null, 'mailLogId' => isset($event['mail_log_id']) ? (int) $event['mail_log_id'] : null, 'eventType' => (string) ($event['event_type'] ?? ''), 'transportMessageId' => isset($event['transport_message_id']) ? (string) $event['transport_message_id'] : null, 'providerEventId' => isset($event['provider_event_id']) ? (string) $event['provider_event_id'] : null];
        if ($includePayloadJson) {
            $payload['payloadJson'] = $this->normalizeJsonString($event['payload_json'] ?? null);
        }
        return $payload + ['occurredAt' => isset($event['occurred_at']) ? (string) $event['occurred_at'] : null, 'createdAt' => isset($event['created_at']) ? (string) $event['created_at'] : null];
    }
    /**
     * @since 1.0.9
     */
    private function normalizeJsonString(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $decoded = json_decode($value, \true);
        if (!is_array($decoded)) {
            return $value;
        }
        return wp_json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
    }
}

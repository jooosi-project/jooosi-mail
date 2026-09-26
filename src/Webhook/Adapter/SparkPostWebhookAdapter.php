<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes SparkPost webhook batches.
 *
 * @link https://developers.sparkpost.com/api/webhooks/
 *
 * @since 0.1.0
 */
#[Service]
class SparkPostWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 332;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'sparkpost';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        return \true;
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return 'unsigned-allowed';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);
        if (!array_is_list($payload) || $payload === []) {
            return parent::parse($request, $connection);
        }
        $normalized = [];
        foreach ($payload as $event) {
            if (!is_array($event)) {
                continue;
            }
            $msys = is_array($event['msys'] ?? null) ? $event['msys'] : [];
            $eventData = [];
            foreach (['message_event', 'track_event'] as $key) {
                if (is_array($msys[$key] ?? null)) {
                    $eventData = $msys[$key];
                    break;
                }
            }
            if ($eventData === []) {
                continue;
            }
            $mailLogId = $this->extractMailLogId($eventData);
            if ($mailLogId === null && is_array($eventData['rcpt_meta'] ?? null)) {
                $mailLogId = $this->extractMailLogId($eventData['rcpt_meta']);
            }
            $normalized[] = ['mail_log_id' => $mailLogId, 'event_type' => $this->normalizeMessageSystemsEvent((string) ($eventData['type'] ?? 'received')), 'transport_message_id' => $this->extractFirstString($eventData, ['message_id']), 'payload' => $event, 'occurred_at' => $this->formatOccurredAt($eventData['timestamp'] ?? null)];
        }
        return $normalized === [] ? parent::parse($request, $connection) : $normalized;
    }
    protected function normalizeMessageSystemsEvent(string $eventType): string
    {
        return match ($this->normalizeEventType($eventType)) {
            'injection' => 'processed',
            'delivery' => 'delivered',
            'open', 'initial_open', 'amp_open' => 'open',
            'click', 'initial_click', 'amp_click' => 'click',
            'list_unsubscribe' => 'unsubscribed',
            default => $this->normalizeEventType($eventType),
        };
    }
}

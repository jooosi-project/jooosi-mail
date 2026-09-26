<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies toSend webhook payloads.
 *
 * @link https://tosend.com/docs/guide/webhooks/
 *
 * @since 0.1.0
 */
#[Service]
final class ToSendWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 321;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'tosend';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \false;
        }
        $signature = trim((string) $request->get_header('x-tosend-signature'));
        if ($signature === '') {
            return \false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $request->get_body(), $secret);
        return hash_equals($expected, $signature) || hash_equals(substr($expected, 7), $signature);
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return $connection->hasWebhookSecret() ? 'hmac-shared-secret' : 'unsupported';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $mail = is_array($payload['mail'] ?? null) ? $payload['mail'] : [];
        $eventType = $payload['type'] ?? $request->get_header('x-tosend-event');
        $eventType = is_scalar($eventType) && trim((string) $eventType) !== '' ? (string) $eventType : 'received';
        return [['mail_log_id' => $this->extractMailLogId($data) ?? $this->extractMailLogId($mail) ?? $this->extractMailLogIdFromCustomHeaders($mail), 'event_type' => $this->normalizeToSendEvent($eventType, $data), 'transport_message_id' => $this->extractFirstString($data, ['message_id']) ?? $this->extractFirstString($mail, ['id']), 'provider_event_id' => null, 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($data['timestamp'] ?? $payload['created_at'] ?? $mail['created_at'] ?? null)]];
    }
    /**
     * @param array<string, mixed> $data
     *
     * @since 0.1.0
     */
    private function normalizeToSendEvent(string $eventType, array $data): string
    {
        return match ($this->normalizeEventType($eventType)) {
            'bounced' => ($data['is_hard_bounce'] ?? null) === \true ? 'hard_bounce' : (($data['is_hard_bounce'] ?? null) === \false ? 'soft_bounce' : 'bounced'),
            'complained' => 'complaint',
            'clicked' => 'click',
            default => $this->normalizeEventType($eventType),
        };
    }
    /**
     * @param array<string, mixed> $mail
     *
     * @since 0.1.0
     */
    private function extractMailLogIdFromCustomHeaders(array $mail): ?int
    {
        $customHeaders = is_array($mail['custom_headers'] ?? null) ? $mail['custom_headers'] : [];
        return $this->extractFirstInt($customHeaders, ['X-Jooosi-Mail-Mail-Log-Id', 'x-jooosi-mail-mail-log-id', 'X-Mail-Log-Id', 'x-mail-log-id']);
    }
}

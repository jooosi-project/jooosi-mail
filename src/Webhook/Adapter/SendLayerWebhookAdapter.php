<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies SendLayer webhook payloads.
 *
 * @link https://developers.sendlayer.com/guides/manage-webhooks.md
 *
 * @since 0.1.0
 */
#[Service]
final class SendLayerWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 334;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'sendlayer';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \true;
        }
        $payload = $this->extractPayload($request);
        $signature = is_array($payload['Signature'] ?? null) ? $payload['Signature'] : [];
        $timestamp = is_scalar($signature['Timestamp'] ?? null) ? (string) $signature['Timestamp'] : '';
        $token = is_scalar($signature['Token'] ?? null) ? (string) $signature['Token'] : '';
        $providedSignature = strtolower((string) ($signature['Signature'] ?? ''));
        if ($timestamp === '' || $token === '' || $providedSignature === '' || !ctype_digit($timestamp)) {
            return \false;
        }
        $expectedSha1 = hash_hmac('sha1', $timestamp . $token, $secret);
        $expectedSha256 = hash_hmac('sha256', $timestamp . $token, $secret);
        return hash_equals($expectedSha1, $providedSignature) || hash_equals($expectedSha256, $providedSignature);
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return $connection->hasWebhookSecret() ? 'hmac-shared-secret' : 'unsigned-allowed';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->extractPayload($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        $eventData = is_array($payload['EventData'] ?? null) ? $payload['EventData'] : [];
        $signature = is_array($payload['Signature'] ?? null) ? $payload['Signature'] : [];
        return [['mail_log_id' => $this->extractMailLogId($eventData), 'event_type' => $this->normalizeSendLayerEvent((string) ($eventData['Event'] ?? 'received')), 'transport_message_id' => $this->extractFirstString($eventData, ['MessageID']), 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($signature['Timestamp'] ?? null)]];
    }
    /**
     * @return array<string, mixed>
     */
    private function extractPayload(WP_REST_Request $request): array
    {
        $payload = $this->decodeJsonBody($request);
        if (is_array($payload['event']['body'] ?? null)) {
            return $payload['event']['body'];
        }
        return $payload;
    }
    private function normalizeSendLayerEvent(string $eventType): string
    {
        return match ($this->normalizeEventType($eventType)) {
            'opened' => 'open',
            'clicked' => 'click',
            'delivered' => 'delivered',
            'bounced' => 'bounce',
            'unsubscribed' => 'unsubscribed',
            'complained' => 'spam_complaint',
            default => $this->normalizeEventType($eventType),
        };
    }
}

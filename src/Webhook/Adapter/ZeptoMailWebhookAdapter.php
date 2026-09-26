<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies ZeptoMail webhook payloads.
 *
 * @link https://www.zoho.com/zeptomail/help/webhooks.html
 *
 * @since 0.1.0
 */
#[Service]
final class ZeptoMailWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 336;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'zeptomail';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \true;
        }
        $signatureHeader = trim((string) $request->get_header('producer-signature'));
        if ($signatureHeader === '') {
            return \false;
        }
        $signatureParts = $this->parseProducerSignature($signatureHeader);
        $timestamp = $signatureParts['ts'] ?? null;
        $signature = $signatureParts['s'] ?? null;
        $algorithm = strtolower((string) ($signatureParts['s-algorithm'] ?? ''));
        if (!is_string($timestamp) || !ctype_digit($timestamp) || !is_string($signature) || $signature === '' || $algorithm !== 'hmacsha256') {
            return \false;
        }
        $timestampMs = (int) $timestamp;
        if ($timestampMs < 1000000000000) {
            $timestampMs *= 1000;
        }
        if (abs((int) round(microtime(\true) * 1000) - $timestampMs) > 300000) {
            return \false;
        }
        $expectedSignature = base64_encode(hash_hmac('sha256', $this->extractSignedPayload($request), $secret, \true));
        return hash_equals($expectedSignature, $signature);
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
        $eventMessage = is_array($payload['event_message'] ?? null) ? $payload['event_message'] : [];
        $emailInfo = is_array($eventMessage['email_info'] ?? null) ? $eventMessage['email_info'] : [];
        $eventData = is_array($payload['event_data'] ?? null) ? $payload['event_data'] : [];
        $details = is_array($eventData['details'] ?? null) ? $eventData['details'] : [];
        $mailLogId = null;
        $clientReference = $emailInfo['client_reference'] ?? null;
        if (is_scalar($clientReference) && (int) $clientReference > 0) {
            $mailLogId = (int) $clientReference;
        }
        $transportMessageId = $this->extractFirstString($emailInfo, ['email_reference']);
        $providerEventId = $this->extractFirstString($eventMessage, ['request_id']);
        return [['mail_log_id' => $mailLogId, 'event_type' => $this->normalizeZeptoMailEvent((string) ($payload['event_name'] ?? 'received')), 'transport_message_id' => $transportMessageId, 'provider_event_id' => $providerEventId, 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($details['time'] ?? $details['modified_time'] ?? $emailInfo['processed_time'] ?? null)]];
    }
    /**
     * @return array<string, string>
     *
     * @since 0.1.0
     */
    private function parseProducerSignature(string $header): array
    {
        $parts = [];
        foreach (explode(';', rawurldecode($header)) as $part) {
            $part = trim($part);
            if ($part === '' || !str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $parts[trim($key)] = trim($value);
        }
        return $parts;
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function extractPayload(WP_REST_Request $request): array
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload !== []) {
            return $payload;
        }
        $body = $request->get_body();
        $params = [];
        parse_str($body, $params);
        foreach (['data', 'payload'] as $key) {
            $value = $params[$key] ?? $request->get_param($key);
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $decoded = json_decode(rawurldecode($value), \true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
    /**
     * @since 0.1.0
     */
    private function extractSignedPayload(WP_REST_Request $request): string
    {
        $body = $request->get_body();
        $params = [];
        parse_str($body, $params);
        foreach (['data', 'payload'] as $key) {
            if (is_string($params[$key] ?? null) && trim((string) $params[$key]) !== '') {
                return rawurldecode((string) $params[$key]);
            }
        }
        return $body;
    }
    /**
     * @since 0.1.0
     */
    private function normalizeZeptoMailEvent(string $eventName): string
    {
        return match ($this->normalizeEventType($eventName)) {
            'soft_bounce', 'softbounce' => 'soft_bounce',
            'hard_bounce', 'hardbounce', 'bounce' => 'hard_bounce',
            'email_open' => 'open',
            'link_click' => 'click',
            'feedback_loop', 'fbl_complaint' => 'spam_complaint',
            'delivered' => 'delivered',
            default => $this->normalizeEventType($eventName),
        };
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use DateTimeImmutable;
use DateTimeZone;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies AhaSend webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class AhaSendWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 339;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'ahasend';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \false;
        }
        $webhookId = trim((string) $request->get_header('webhook-id'));
        $signature = trim((string) $request->get_header('webhook-signature'));
        $timestamp = trim((string) $request->get_header('webhook-timestamp'));
        if ($webhookId === '' || $signature === '' || $timestamp === '' || !ctype_digit($timestamp) || (int) $timestamp <= 0) {
            return \false;
        }
        $expectedSignature = $this->sign($webhookId, $timestamp, $request->get_body(), $secret);
        return hash_equals($expectedSignature, $signature);
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
        return [['mail_log_id' => $this->extractMailLogId($payload), 'event_type' => $this->normalizeAhaSendEventType((string) ($payload['type'] ?? 'received')), 'transport_message_id' => $data === [] ? null : $this->extractFirstString($data, ['id']), 'payload' => $payload, 'occurred_at' => $this->formatAhaSendOccurredAt($payload['timestamp'] ?? null)]];
    }
    /**
     * @since 0.1.0
     */
    private function sign(string $webhookId, string $timestamp, string $payload, string $secret): string
    {
        $signaturePayload = $webhookId . '.' . $timestamp . '.' . $payload;
        $hash = hash_hmac('sha256', $signaturePayload, $secret);
        return 'v1,' . base64_encode(pack('H*', $hash));
    }
    /**
     * @since 0.1.0
     */
    private function normalizeAhaSendEventType(string $eventType): string
    {
        return match (trim($eventType)) {
            'message.reception' => 'received',
            'message.delivered' => 'delivered',
            'message.transient_error' => 'deferred',
            'message.failed' => 'failed',
            'message.bounced' => 'bounce',
            'message.suppressed' => 'dropped',
            'message.clicked' => 'click',
            'message.opened' => 'open',
            default => $this->normalizeEventType($eventType),
        };
    }
    /**
     * @since 0.1.0
     */
    private function formatAhaSendOccurredAt(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return $this->formatOccurredAt($value);
        }
        $timestamp = trim($value);
        $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $timestamp);
        if (!$date instanceof DateTimeImmutable) {
            $truncatedTimestamp = substr($timestamp, 0, 26) . 'Z';
            $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uT', $truncatedTimestamp);
        }
        if ($date instanceof DateTimeImmutable) {
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        return $this->formatOccurredAt($value);
    }
}

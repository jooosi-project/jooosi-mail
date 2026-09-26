<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes SendGrid event webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class SendGridWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 400;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'sendgrid';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \true;
        }
        $signature = trim((string) $request->get_header('x-twilio-email-event-webhook-signature'));
        $timestamp = trim((string) $request->get_header('x-twilio-email-event-webhook-timestamp'));
        if ($signature === '' || $timestamp === '') {
            return \false;
        }
        $publicKey = openssl_pkey_get_public($this->formatPublicKey($secret));
        if ($publicKey === \false) {
            return \false;
        }
        $decodedSignature = base64_decode($signature, \true);
        if (!is_string($decodedSignature) || $decodedSignature === '') {
            return \false;
        }
        return openssl_verify($timestamp . $request->get_body(), $decodedSignature, $publicKey, \OPENSSL_ALGO_SHA256) === 1;
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return $connection->hasWebhookSecret() ? 'twilio-public-key' : 'unsigned-allowed';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);
        $events = array_is_list($payload) ? $payload : [$payload];
        $normalized = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $transportMessageId = $this->extractFirstString($event, ['sg_message_id', 'smtp-id', 'smtp_id', 'message_id']);
            $providerEventId = $this->extractFirstString($event, ['sg_event_id']);
            $normalized[] = ['mail_log_id' => $this->extractMailLogId($event), 'event_type' => $this->normalizeProviderEventType((string) ($event['event'] ?? $event['type'] ?? 'received')), 'transport_message_id' => $transportMessageId, 'provider_event_id' => $providerEventId, 'payload' => $event, 'occurred_at' => $this->formatOccurredAt($event['timestamp'] ?? null)];
        }
        return $normalized === [] ? parent::parse($request, $connection) : $normalized;
    }
    /**
     * @since 0.1.0
     */
    private function normalizeProviderEventType(string $eventType): string
    {
        return match (strtolower(trim($eventType))) {
            'spamreport' => 'spam_report',
            default => $this->normalizeEventType($eventType),
        };
    }
    /**
     * @since 0.1.0
     */
    private function formatPublicKey(string $secret): string
    {
        if (str_contains($secret, 'BEGIN PUBLIC KEY')) {
            return trim($secret) . "\n";
        }
        $normalized = preg_replace('/\s+/', '', $secret) ?? $secret;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($normalized, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies Mailomat webhook payloads.
 *
 * @link https://api.mailomat.swiss/docs/#tag/webhook-security
 *
 * @since 0.1.0
 */
#[Service]
final class MailomatWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 341;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'mailomat';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \false;
        }
        $event = trim((string) $request->get_header('x-mom-webhook-event'));
        $webhookId = trim((string) $request->get_header('x-mom-webhook-id'));
        $timestamp = trim((string) $request->get_header('x-mom-webhook-timestamp'));
        $signatureHeader = trim((string) $request->get_header('x-mom-webhook-signature'));
        if ($event === '' || $webhookId === '' || $timestamp === '' || $signatureHeader === '' || !str_contains($signatureHeader, '=')) {
            return \false;
        }
        [$algorithm, $signature] = explode('=', $signatureHeader, 2);
        $algorithm = strtolower(trim($algorithm));
        $signature = trim($signature);
        if ($algorithm === '' || $signature === '' || !in_array($algorithm, hash_hmac_algos(), \true)) {
            return \false;
        }
        return hash_equals(hash_hmac($algorithm, $webhookId . '.' . $event . '.' . $timestamp, $secret), $signature);
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return 'hmac-shared-secret';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        $providerPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $eventType = is_scalar($payload['eventType'] ?? null) ? (string) $payload['eventType'] : trim((string) $request->get_header('x-mom-webhook-event'));
        if ($eventType === '') {
            $eventType = 'received';
        }
        return [['mail_log_id' => $this->extractMailLogId($payload) ?? $this->extractMailLogId($providerPayload), 'event_type' => $this->normalizeMailomatEvent($eventType), 'transport_message_id' => $this->extractFirstString($payload, ['messageId', 'messageUuid']), 'provider_event_id' => $this->extractFirstString($payload, ['id']), 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($payload['occurredAt'] ?? null)]];
    }
    /**
     * @since 0.1.0
     */
    private function normalizeMailomatEvent(string $eventType): string
    {
        return match ($this->normalizeEventType($eventType)) {
            'accepted' => 'received',
            'not_accepted' => 'dropped',
            'failure_tmp' => 'deferred',
            'failure_perm' => 'bounce',
            'opened' => 'open',
            'clicked' => 'click',
            default => $this->normalizeEventType($eventType),
        };
    }
}

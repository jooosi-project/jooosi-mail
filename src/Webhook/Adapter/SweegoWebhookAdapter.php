<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies Sweego webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class SweegoWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 337;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'sweego';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \false;
        }
        $decodedSecret = base64_decode($secret, \true);
        if (!is_string($decodedSecret) || $decodedSecret === '') {
            return \false;
        }
        $webhookId = trim((string) $request->get_header('webhook-id'));
        $webhookTimestamp = trim((string) $request->get_header('webhook-timestamp'));
        $signature = trim((string) $request->get_header('webhook-signature'));
        if ($webhookId === '' || $webhookTimestamp === '' || $signature === '') {
            return \false;
        }
        $contentToSign = $webhookId . '.' . $webhookTimestamp . '.' . $request->get_body();
        $computedSignature = base64_encode(hash_hmac('sha256', $contentToSign, $decodedSecret, \true));
        return hash_equals($computedSignature, $signature);
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return $connection->hasWebhookSecret() ? 'hmac-base64-shared-secret' : 'unsupported';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        $headers = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];
        $mailLogId = $this->extractMailLogId($payload);
        if ($mailLogId === null && $headers !== []) {
            $mailLogId = $this->extractFirstInt($headers, ['mail_log_id', 'jooosi_mail_mail_log_id', 'x-mail-log-id', 'x-jooosi-mail-mail-log-id']);
        }
        return [['mail_log_id' => $mailLogId, 'event_type' => $this->normalizeEventType((string) ($payload['event_type'] ?? 'received')), 'transport_message_id' => $headers === [] ? null : $this->extractFirstString($headers, ['x-transaction-id']), 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($payload['timestamp'] ?? null)]];
    }
}

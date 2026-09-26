<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies Mailgun webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class MailgunWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 350;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'mailgun';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \false;
        }
        $body = $this->decodeJsonBody($request);
        $signature = is_array($body['signature'] ?? null) ? $body['signature'] : $request->get_params()['signature'] ?? null;
        if (!is_array($signature)) {
            return \false;
        }
        $timestamp = (string) ($signature['timestamp'] ?? '');
        $token = (string) ($signature['token'] ?? '');
        $actualSignature = (string) ($signature['signature'] ?? '');
        if ($timestamp === '' || $token === '' || $actualSignature === '') {
            return \false;
        }
        $expectedSignature = hash_hmac('sha256', $timestamp . $token, $secret);
        return hash_equals($expectedSignature, $actualSignature);
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        $secret = $connection->getWebhookSecret();
        return $secret !== null && $secret !== '' ? 'hmac-shared-secret' : 'unsupported';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $body = $this->decodeJsonBody($request);
        $eventData = is_array($body['event-data'] ?? null) ? $body['event-data'] : [];
        if ($eventData === []) {
            $eventData = is_array($request->get_param('event-data')) ? $request->get_param('event-data') : [];
        }
        if ($eventData === []) {
            return parent::parse($request, $connection);
        }
        $transportMessageId = $this->extractFirstString($eventData, ['message_id']) ?? (is_scalar($this->extractNestedValue($eventData, ['message', 'headers', 'message-id'])) ? (string) $this->extractNestedValue($eventData, ['message', 'headers', 'message-id']) : null);
        $providerEventId = $this->extractFirstString($eventData, ['id']);
        return [['mail_log_id' => $this->extractMailLogId($eventData), 'event_type' => $this->normalizeEventType((string) ($eventData['event'] ?? 'received')), 'transport_message_id' => $transportMessageId, 'provider_event_id' => $providerEventId, 'payload' => $body === [] ? $eventData : $body, 'occurred_at' => $this->formatOccurredAt($eventData['timestamp'] ?? null)]];
    }
}

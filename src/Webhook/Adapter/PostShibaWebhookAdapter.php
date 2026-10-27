<?php

declare(strict_types=1);

namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;

/**
 * Verifies and normalizes PostShiba delivery webhooks.
 *
 * @link https://www.postshiba.com/docs/webhooks/verify-webhooks-requests
 * @link https://www.postshiba.com/docs/webhooks/event-types
 *
 * @since 1.0.13
 */
#[Service]
final class PostShibaWebhookAdapter extends AbstractWebhookAdapter
{
    /**
     * @since 1.0.13
     */
    #[Override]
    public function getPriority(): int
    {
        return 410;
    }

    /**
     * @since 1.0.13
     */
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'postshiba';
    }

    /**
     * @since 1.0.13
     */
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();

        if ($secret === null || $secret === '') {
            return false;
        }

        $timestamp = trim((string) $request->get_header('x-capsule-timestamp'));
        $signature = trim((string) $request->get_header('x-capsule-signature'));

        if ($timestamp === '' || ! ctype_digit($timestamp) || (int) $timestamp <= 0 || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $providedSignature = substr($signature, 7);

        if (strlen($providedSignature) !== 64 || ! ctype_xdigit($providedSignature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $request->get_body(), $secret);

        return hash_equals($expectedSignature, strtolower($providedSignature));
    }

    /**
     * @since 1.0.13
     */
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return $connection->hasWebhookSecret() ? 'hmac-shared-secret' : 'unsupported';
    }

    /**
     * @since 1.0.13
     */
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);

        if ($payload === []) {
            return parent::parse($request, $connection);
        }

        $events = array_is_list($payload) ? $payload : [$payload];
        $normalized = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $transportMessageId = $this->extractFirstString($event, ['sg_message_id', 'smtp-id', 'smtp_id', 'message_id']);

            $normalized[] = [
                'mail_log_id' => $this->extractMailLogId($event),
                'event_type' => $this->normalizePostShibaEventType((string) ($event['event'] ?? $event['type'] ?? 'received')),
                'transport_message_id' => $transportMessageId,
                'provider_event_id' => $this->extractFirstString($event, ['sg_event_id']),
                'payload' => $event,
                'occurred_at' => $this->formatOccurredAt($event['timestamp'] ?? null),
            ];
        }

        return $normalized === [] ? parent::parse($request, $connection) : $normalized;
    }

    /**
     * @since 1.0.13
     */
    private function normalizePostShibaEventType(string $eventType): string
    {
        return match (strtolower(trim($eventType))) {
            'spamreport' => 'spam_report',
            default => $this->normalizeEventType($eventType),
        };
    }
}

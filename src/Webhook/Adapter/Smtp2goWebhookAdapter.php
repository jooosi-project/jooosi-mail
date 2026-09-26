<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes SMTP2GO webhook payloads.
 *
 * @link https://developers.smtp2go.com/docs/webhooks-overview
 *
 * @since 0.1.0
 */
#[Service]
final class Smtp2goWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 333;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'smtp2go';
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
        $payload = $this->extractPayload($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        $mailLogId = $this->extractMailLogId($payload) ?? $this->extractFirstInt($payload, ['x_jooosi_mail_mail_log_id', 'x_mail_log_id']);
        return [['mail_log_id' => $mailLogId, 'event_type' => $this->normalizeSmtp2goEvent((string) ($payload['event'] ?? 'received')), 'transport_message_id' => $this->extractFirstString($payload, ['email_id', 'message-id', 'message_id']), 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($payload['time'] ?? $payload['sendtime'] ?? null)]];
    }
    /**
     * @return array<string, mixed>
     */
    private function extractPayload(WP_REST_Request $request): array
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload !== []) {
            return $payload;
        }
        $body = $request->get_body();
        $parsed = [];
        parse_str($body, $parsed);
        return is_array($parsed) ? $parsed : [];
    }
    private function normalizeSmtp2goEvent(string $eventType): string
    {
        return match ($this->normalizeEventType($eventType)) {
            'spam' => 'spam_complaint',
            'unsubscribe' => 'unsubscribed',
            'resubscribe' => 'resubscribed',
            'reject' => 'rejected',
            default => $this->normalizeEventType($eventType),
        };
    }
}

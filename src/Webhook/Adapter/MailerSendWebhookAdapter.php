<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies MailerSend webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class MailerSendWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 342;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'mailersend';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \true;
        }
        $signature = trim((string) $request->get_header('signature'));
        if ($signature === '') {
            return \false;
        }
        return hash_equals(hash_hmac('sha256', $request->get_body(), $secret), $signature);
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return $connection->hasWebhookSecret() ? 'hmac-shared-secret' : 'unsigned-allowed';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        $message = is_array($this->extractNestedValue($payload, ['data', 'email', 'message'])) ? $this->extractNestedValue($payload, ['data', 'email', 'message']) : [];
        $variables = is_array($this->extractNestedValue($payload, ['data', 'email', 'variables'])) ? $this->extractNestedValue($payload, ['data', 'email', 'variables']) : [];
        return [['mail_log_id' => $this->extractMailLogId($variables) ?? $this->extractMailLogId($payload), 'event_type' => $this->normalizeEventType((string) ($payload['type'] ?? 'received')), 'transport_message_id' => is_array($message) ? $this->extractFirstString($message, ['id']) : null, 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($payload['created_at'] ?? null)]];
    }
}

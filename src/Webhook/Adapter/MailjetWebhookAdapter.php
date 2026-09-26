<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes Mailjet webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class MailjetWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 338;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'mailjet';
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
        $payload = $this->decodeJsonBody($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        $mailLogId = $this->extractMailLogId($payload);
        $payloadMetadata = $this->decodePayloadMetadata($payload['Payload'] ?? null);
        if ($mailLogId === null && is_array($payloadMetadata)) {
            $mailLogId = $this->extractMailLogId($payloadMetadata);
        }
        return [['mail_log_id' => $mailLogId, 'event_type' => $this->normalizeEventType((string) ($payload['event'] ?? 'received')), 'transport_message_id' => $this->extractFirstString($payload, ['MessageID']), 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($payload['time'] ?? null)]];
    }
    /**
     * @return array<string, mixed>|null
     *
     * @since 0.1.0
     */
    private function decodePayloadMetadata(mixed $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $decoded = json_decode($value, \true);
        return is_array($decoded) ? $decoded : null;
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes Postmark webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class PostmarkWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    /**
     * @var list<string>
     */
    private const ALLOWED_IPS = ['3.134.147.250', '50.31.156.6', '50.31.156.77', '18.217.206.57', '127.0.0.1', '::1'];
    #[Override]
    public function getPriority(): int
    {
        return 300;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'postmark';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $remoteAddress = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        return $remoteAddress !== '' && in_array(trim($remoteAddress), self::ALLOWED_IPS, \true);
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return 'ip-allowlist';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        return [['mail_log_id' => $this->extractMailLogId($payload), 'event_type' => $this->normalizeRecordType((string) ($payload['RecordType'] ?? 'received')), 'transport_message_id' => $this->extractFirstString($payload, ['MessageID', 'OriginalMessageID', 'MessageId']), 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($payload['ReceivedAt'] ?? $payload['DeliveredAt'] ?? $payload['BouncedAt'] ?? $payload['ChangedAt'] ?? $payload['SubmittedAt'] ?? null)]];
    }
    /**
     * @since 0.1.0
     */
    private function normalizeRecordType(string $recordType): string
    {
        return match (trim($recordType)) {
            'Delivery' => 'delivered',
            'Bounce' => 'bounce',
            'SpamComplaint' => 'spam_complaint',
            'Open' => 'open',
            'Click' => 'click',
            'SubscriptionChange' => 'subscription_change',
            default => $this->normalizeEventType($recordType),
        };
    }
}

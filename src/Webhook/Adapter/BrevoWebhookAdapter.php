<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies Brevo webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class BrevoWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    /**
     * @var list<string>
     */
    private const ALLOWED_IP_RANGES = ['1.179.112.0/20', '172.246.240.0/20', '127.0.0.1', '::1'];
    #[Override]
    public function getPriority(): int
    {
        return 360;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'brevo';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        return $this->requestMatchesIpRanges(self::ALLOWED_IP_RANGES);
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
        return [['mail_log_id' => $this->extractMailLogId($payload), 'event_type' => $this->normalizeEventType((string) ($payload['event'] ?? 'received')), 'transport_message_id' => $this->extractFirstString($payload, ['message-id', 'message_id']), 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($payload['ts_event'] ?? null)]];
    }
}

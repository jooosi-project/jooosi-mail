<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes Mailtrap webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class MailtrapWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 345;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'mailtrap';
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
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];
        if ($events === []) {
            return parent::parse($request, $connection);
        }
        $normalized = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $mailLogId = $this->extractMailLogId($event);
            $customVariables = is_array($event['custom_variables'] ?? null) ? $event['custom_variables'] : [];
            if ($mailLogId === null) {
                $mailLogId = $this->extractMailLogId($customVariables);
            }
            $normalized[] = ['mail_log_id' => $mailLogId, 'event_type' => $this->normalizeEventType((string) ($event['event'] ?? 'received')), 'transport_message_id' => $this->extractFirstString($event, ['message_id']), 'payload' => $event, 'occurred_at' => $this->formatOccurredAt($event['timestamp'] ?? null)];
        }
        return $normalized === [] ? parent::parse($request, $connection) : $normalized;
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies Mandrill webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class MandrillWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 341;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'mandrill';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $signature = trim((string) $request->get_header('x-mandrill-signature'));
        $payload = $this->extractMandrillEventsPayload($request);
        if ($signature === '' || $payload === null) {
            return \false;
        }
        $secret = $payload === '[]' ? 'test-webhook' : $connection->getWebhookSecret();
        if (!is_string($secret) || $secret === '') {
            return \false;
        }
        return hash_equals($this->computeSignature($request, ['mandrill_events' => $payload], $secret), $signature);
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        return $connection->hasWebhookSecret() ? 'hmac-sha1-shared-secret' : 'unsupported';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->extractMandrillEventsPayload($request);
        if ($payload === null) {
            return parent::parse($request, $connection);
        }
        $events = json_decode($payload, \true);
        if (!is_array($events) || $events === []) {
            return [];
        }
        $normalized = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $message = is_array($event['msg'] ?? null) ? $event['msg'] : [];
            $normalized[] = ['mail_log_id' => $this->extractMailLogId($event), 'event_type' => $this->normalizeEventType((string) ($event['event'] ?? 'received')), 'transport_message_id' => is_array($message) ? $this->extractFirstString($message, ['_id']) : null, 'payload' => $event, 'occurred_at' => is_array($message) ? $this->formatOccurredAt($message['ts'] ?? null) : $this->formatOccurredAt(null)];
        }
        return $normalized === [] ? parent::parse($request, $connection) : $normalized;
    }
    /**
     * @return string|null
     *
     * @since 0.1.0
     */
    private function extractMandrillEventsPayload(WP_REST_Request $request): ?string
    {
        $payload = $request->get_param('mandrill_events');
        if (!is_string($payload)) {
            return null;
        }
        $payload = trim($payload);
        return $payload !== '' ? $payload : null;
    }
    /**
     * @param array<string, string> $parameters
     *
     * @since 0.1.0
     */
    private function computeSignature(WP_REST_Request $request, array $parameters, string $secret): string
    {
        ksort($parameters);
        $signedData = rest_url(ltrim($request->get_route(), '/'));
        foreach ($parameters as $key => $value) {
            $signedData .= $key . $value;
        }
        return base64_encode(hash_hmac('sha1', $signedData, $secret, \true));
    }
}

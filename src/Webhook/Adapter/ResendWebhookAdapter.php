<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use Override;
use WP_REST_Request;
/**
 * Normalizes and verifies Resend webhook payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class ResendWebhookAdapter extends \JooosiMail\Webhook\Adapter\AbstractWebhookAdapter
{
    #[Override]
    public function getPriority(): int
    {
        return 320;
    }
    #[Override]
    public function supports(Connection $connection): bool
    {
        return $connection->profileKey === 'resend';
    }
    #[Override]
    public function verify(WP_REST_Request $request, Connection $connection): bool
    {
        $secret = $connection->getWebhookSecret();
        if ($secret === null || $secret === '') {
            return \false;
        }
        $svixId = (string) $request->get_header('svix-id');
        $svixTimestamp = (string) $request->get_header('svix-timestamp');
        $signatureHeader = (string) $request->get_header('svix-signature');
        if ($svixId === '' || $svixTimestamp === '' || $signatureHeader === '') {
            return \false;
        }
        $secret = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $decodedSecret = base64_decode($secret, \true);
        if ($decodedSecret === \false) {
            $decodedSecret = $secret;
        }
        $signedContent = sprintf('%s.%s.%s', $svixId, $svixTimestamp, $request->get_body());
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $decodedSecret, \true));
        foreach ($this->extractSignatures($signatureHeader) as $signature) {
            if (hash_equals($expected, $signature)) {
                return \true;
            }
        }
        return \false;
    }
    #[Override]
    public function describeVerification(Connection $connection): string
    {
        $secret = $connection->getWebhookSecret();
        return $secret !== null && $secret !== '' ? 'svix-shared-secret' : 'unsupported';
    }
    #[Override]
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload === []) {
            return parent::parse($request, $connection);
        }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventSource = $data !== [] ? $data : $payload;
        return [['mail_log_id' => $this->extractMailLogId($eventSource), 'event_type' => $this->normalizeEventType((string) ($payload['type'] ?? $data['type'] ?? 'received')), 'transport_message_id' => $this->extractFirstString($eventSource, ['email_id', 'message_id']), 'provider_event_id' => $this->extractFirstString($eventSource, ['id']), 'payload' => $payload, 'occurred_at' => $this->formatOccurredAt($eventSource['created_at'] ?? $payload['created_at'] ?? null)]];
    }
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function extractSignatures(string $signatureHeader): array
    {
        preg_match_all('/v1[=,]([^,\s]+)/', $signatureHeader, $matches);
        return array_values(array_filter($matches[1] ?? [], static fn(string $signature): bool => $signature !== ''));
    }
}

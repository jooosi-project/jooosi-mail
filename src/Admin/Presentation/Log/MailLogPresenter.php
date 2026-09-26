<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Presentation\Log;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Projects raw mail log rows into the public admin API representation.
 *
 * @since 1.0.9
 */
#[Service]
final class MailLogPresenter
{
    /**
     * @param list<array<string, mixed>> $logs
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function presentMany(array $logs): array
    {
        return array_map(fn(array $log): array => $this->present($log), $logs);
    }
    /**
     * @param array<string, mixed> $log
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function present(array $log): array
    {
        $messageBodies = $this->extractPayloadBodies($log['payload_json'] ?? null);
        return ['id' => (int) ($log['id'] ?? 0), 'source' => (string) ($log['source'] ?? ''), 'subject' => (string) ($log['subject'] ?? ''), 'status' => (string) ($log['status'] ?? ''), 'finalConnectionId' => isset($log['final_connection_id']) ? (int) $log['final_connection_id'] : null, 'connectionName' => isset($log['connection_name']) ? (string) $log['connection_name'] : null, 'connectionProfileKey' => isset($log['connection_profile_key']) ? (string) $log['connection_profile_key'] : null, 'transportMessageId' => isset($log['transport_message_id']) ? (string) $log['transport_message_id'] : null, 'lastError' => isset($log['last_error']) ? (string) $log['last_error'] : null, 'toAddresses' => $this->extractAddressList($this->decodeJsonArray($log['recipients_json'] ?? null)), 'fromAddresses' => $this->extractAddressListFromPayload($log['payload_json'] ?? null, 'from'), 'ccAddresses' => $this->extractAddressListFromPayload($log['payload_json'] ?? null, 'cc'), 'bccAddresses' => $this->extractAddressListFromPayload($log['payload_json'] ?? null, 'bcc'), 'replyToAddresses' => $this->extractAddressListFromPayload($log['payload_json'] ?? null, 'replyTo'), 'textBody' => $messageBodies['textBody'], 'htmlBody' => $messageBodies['htmlBody'], 'createdAt' => isset($log['created_at']) ? (string) $log['created_at'] : null, 'queuedAt' => isset($log['queued_at']) ? (string) $log['queued_at'] : null, 'sentAt' => isset($log['sent_at']) ? (string) $log['sent_at'] : null, 'updatedAt' => isset($log['updated_at']) ? (string) $log['updated_at'] : null];
    }
    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     *
     * @since 1.0.9
     */
    private function extractAddressListFromPayload(mixed $payloadJson, string $key): array
    {
        $payload = $this->decodeJsonArray($payloadJson);
        if ($payload === []) {
            return [];
        }
        return $this->extractAddressList(is_array($payload[$key] ?? null) ? $payload[$key] : []);
    }
    /**
     * @return array{textBody: ?string, htmlBody: ?string}
     *
     * @since 1.0.9
     */
    private function extractPayloadBodies(mixed $payloadJson): array
    {
        $textBody = $this->extractPayloadString($payloadJson, 'textBody');
        $htmlBody = $this->extractPayloadString($payloadJson, 'htmlBody');
        if ($htmlBody === null && $textBody !== null && $this->payloadUsesHtmlContentType($payloadJson)) {
            return ['textBody' => null, 'htmlBody' => $textBody];
        }
        return ['textBody' => $textBody, 'htmlBody' => $htmlBody];
    }
    /**
     * @since 1.0.9
     */
    private function extractPayloadString(mixed $payloadJson, string $key): ?string
    {
        $payload = $this->decodeJsonArray($payloadJson);
        if ($payload === []) {
            return null;
        }
        $value = $payload[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
    /**
     * @since 1.0.9
     */
    private function payloadUsesHtmlContentType(mixed $payloadJson): bool
    {
        $payload = $this->decodeJsonArray($payloadJson);
        if ($payload === []) {
            return \false;
        }
        $contentType = $this->extractContentTypeHeader($payload['headers'] ?? null) ?? $this->extractContentTypeHeader($payload['metadata']['raw']['headers'] ?? null);
        if ($contentType === null) {
            return \false;
        }
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0] ?? $contentType));
        return 'text/html' === $mediaType;
    }
    /**
     * @since 1.0.9
     */
    private function extractContentTypeHeader(mixed $headers): ?string
    {
        if (is_array($headers)) {
            $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? null;
            if (is_string($contentType) && trim($contentType) !== '') {
                return trim($contentType);
            }
            foreach ($headers as $headerLine) {
                if (!is_string($headerLine)) {
                    continue;
                }
                $normalizedHeaderLine = trim($headerLine);
                if (!str_starts_with(strtolower($normalizedHeaderLine), 'content-type:')) {
                    continue;
                }
                $value = trim(substr($normalizedHeaderLine, strlen('content-type:')));
                return $value !== '' ? $value : null;
            }
            return null;
        }
        if (!is_string($headers) || trim($headers) === '') {
            return null;
        }
        foreach (preg_split('/\r\n|\r|\n/', $headers) ?: [] as $headerLine) {
            $normalizedHeaderLine = trim((string) $headerLine);
            if (!str_starts_with(strtolower($normalizedHeaderLine), 'content-type:')) {
                continue;
            }
            $value = trim(substr($normalizedHeaderLine, strlen('content-type:')));
            return $value !== '' ? $value : null;
        }
        return null;
    }
    /**
     * @param array<int, array<string, mixed>> $addresses
     * @return list<string>
     *
     * @since 1.0.9
     */
    private function extractAddressList(array $addresses): array
    {
        $results = [];
        foreach ($addresses as $address) {
            $emailAddress = isset($address['address']) ? trim((string) $address['address']) : '';
            if ($emailAddress === '') {
                continue;
            }
            $results[] = $emailAddress;
        }
        return $results;
    }
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, \true);
        return is_array($decoded) ? $decoded : [];
    }
}

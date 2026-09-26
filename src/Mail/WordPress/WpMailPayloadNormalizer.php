<?php

declare (strict_types=1);
namespace JooosiMail\Mail\WordPress;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\ValueObject\MailAddress;
use JooosiMail\Mail\ValueObject\MailAttachment;
use JooosiMail\Mail\ValueObject\MailRequest;
/**
 * Converts WordPress mail arguments into Jooosi Mail requests.
 *
 * @since 0.1.0
 */
#[Service]
final class WpMailPayloadNormalizer
{
    /**
     * @param array<string, mixed> $args
     *
     * @since 0.1.0
     */
    public function normalize(array $args): MailRequest
    {
        $headers = $this->normalizeHeaders($args['headers'] ?? []);
        $contentType = (string) ($this->getHeader($headers, 'Content-Type') ?? 'text/plain');
        $message = (string) ($args['message'] ?? '');
        $from = $this->extractHeaderAddresses($this->getHeader($headers, 'From'));
        $isHtmlContentType = $this->isHtmlContentType($contentType);
        $bodies = $this->normalizeBodies($message, $isHtmlContentType);
        $defaultFromApplied = \false;
        if ($from === []) {
            $from[] = new MailAddress((string) get_option('admin_email'), (string) get_option('blogname'));
            $defaultFromApplied = \true;
        }
        $mailRequest = new MailRequest(from: $from, to: $this->parseAddresses($args['to'] ?? []), cc: $this->extractHeaderAddresses($this->getHeader($headers, 'Cc')), bcc: $this->extractHeaderAddresses($this->getHeader($headers, 'Bcc')), replyTo: $this->extractHeaderAddresses($this->getHeader($headers, 'Reply-To')), subject: (string) ($args['subject'] ?? ''), textBody: $bodies['textBody'], htmlBody: $bodies['htmlBody'], attachments: $this->normalizeAttachments($args['attachments'] ?? []), headers: $headers, source: 'wp_mail', metadata: ['raw' => $args, 'jooosi_mail_default_from_applied' => $defaultFromApplied]);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Intentional by design; hook name is well-prefixed with `f!jooosi-mail/`.
        $filteredMailRequest = apply_filters('f!jooosi-mail/mail:normalize.request', $mailRequest, $args, $headers);
        return $filteredMailRequest instanceof MailRequest ? $filteredMailRequest : $mailRequest;
    }
    /**
     * @param string|array<int, string> $headers
     * @return array<string, string>
     *
     * @since 0.1.0
     */
    private function normalizeHeaders(string|array $headers): array
    {
        $normalized = [];
        $lines = is_array($headers) ? $headers : (preg_split('/\r\n|\r|\n/', $headers) ?: []);
        foreach ($lines as $line) {
            if (!is_string($line) || !str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $normalized[$name] = $value;
        }
        return $normalized;
    }
    /**
     * @param array<string, string> $headers
     *
     * @since 0.1.0
     */
    private function getHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                return $value;
            }
        }
        return null;
    }
    /**
     * @return array{textBody: ?string, htmlBody: ?string}
     *
     * @since 0.1.0
     */
    private function normalizeBodies(string $message, bool $isHtmlContentType): array
    {
        return ['textBody' => $isHtmlContentType ? null : $message, 'htmlBody' => $isHtmlContentType ? $message : null];
    }
    /**
     * @param string|array<int, string> $addresses
     * @return list<MailAddress>
     *
     * @since 0.1.0
     */
    private function parseAddresses(string|array $addresses): array
    {
        $rawAddresses = is_array($addresses) ? $addresses : explode(',', $addresses);
        $parsed = [];
        foreach ($rawAddresses as $rawAddress) {
            $rawAddress = trim((string) $rawAddress);
            if ($rawAddress === '') {
                continue;
            }
            if (preg_match('/^(.+)\s*<(.+)>$/', $rawAddress, $matches) === 1) {
                $parsed[] = new MailAddress(trim($matches[2]), trim($matches[1], '" '));
                continue;
            }
            $parsed[] = new MailAddress($rawAddress);
        }
        return $parsed;
    }
    /**
     * @return list<MailAddress>
     *
     * @since 0.1.0
     */
    private function extractHeaderAddresses(?string $header): array
    {
        if ($header === null || $header === '') {
            return [];
        }
        return $this->parseAddresses($header);
    }
    /**
     * @param mixed $attachments
     * @return list<MailAttachment>
     *
     * @since 0.1.0
     */
    private function normalizeAttachments(mixed $attachments): array
    {
        if (!is_array($attachments)) {
            return [];
        }
        $normalized = [];
        foreach ($attachments as $attachment) {
            if (is_string($attachment) && $attachment !== '') {
                $normalized[] = new MailAttachment($attachment);
            }
        }
        return $normalized;
    }
    /**
     * @since 0.1.0
     */
    private function isHtmlContentType(string $contentType): bool
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0] ?? $contentType));
        return 'text/html' === $mediaType;
    }
}

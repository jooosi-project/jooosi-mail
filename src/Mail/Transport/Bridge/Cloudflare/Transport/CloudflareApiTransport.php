<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Cloudflare\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\HttpTransportException;
use JooosiMailDeps\Symfony\Component\Mailer\Header\MetadataHeader;
use JooosiMailDeps\Symfony\Component\Mailer\Header\TagHeader;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractApiTransport;
use JooosiMailDeps\Symfony\Component\Mime\Address;
use JooosiMailDeps\Symfony\Component\Mime\Email;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * Cloudflare Email Service REST API transport.
 *
 * Cloudflare's REST API does not currently return a provider message ID, so
 * Symfony's generated message ID remains the local correlation value.
 *
 * @since 0.1.0
 */
final class CloudflareApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'api.cloudflare.com';
    /** @var list<string> */
    private const ALLOWED_HEADERS = ['archived-at', 'auto-submitted', 'comments', 'content-language', 'importance', 'in-reply-to', 'keywords', 'list-archive', 'list-help', 'list-id', 'list-owner', 'list-post', 'list-subscribe', 'list-unsubscribe', 'list-unsubscribe-post', 'organization', 'precedence', 'references', 'require-recipient-valid-since', 'sensitivity'];
    public function __construct(private readonly string $accountId, private readonly string $apiToken, ?HttpClientInterface $httpClient = null, ?EventDispatcherInterface $eventDispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }
    public function __toString(): string
    {
        return sprintf('cloudflare+api://%s', $this->accountId);
    }
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', $this->getEndpoint(), ['headers' => ['Authorization' => 'Bearer ' . $this->apiToken, 'Content-Type' => 'application/json'], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the Cloudflare Email Service API.', $response, 0, $transportException);
        }
        try {
            $result = $response->toArray(\false);
        } catch (DecodingExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send email via Cloudflare Email Service: ' . esc_html($response->getContent(\false)) . sprintf(' (code %d).', $statusCode), $response, 0, $exception);
        }
        if ($statusCode >= 400 || ($result['success'] ?? \false) !== \true) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send email via Cloudflare Email Service: ' . esc_html($this->formatErrorMessage($result)) . sprintf(' (code %d).', $statusCode), $response);
        }
        return $response;
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = ['from' => $this->formatSender($email, $envelope), 'to' => $this->formatPrimaryRecipients($email, $envelope), 'subject' => (string) $email->getSubject()];
        if ($email->getCc() !== []) {
            $payload['cc'] = array_map(static fn(Address $address): string => $address->getAddress(), $email->getCc());
        }
        if ($email->getBcc() !== []) {
            $payload['bcc'] = array_map(static fn(Address $address): string => $address->getAddress(), $email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $replyToAddresses = $email->getReplyTo();
            $replyTo = reset($replyToAddresses);
            if ($replyTo instanceof Address) {
                $payload['reply_to'] = $this->formatAddress($replyTo);
            }
        }
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['html'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['text'] = $email->getTextBody();
        }
        if ($email->getAttachments() !== []) {
            $payload['attachments'] = $this->getAttachments($email);
        }
        $headers = $this->getHeaders($email);
        if ($headers !== []) {
            $payload['headers'] = $headers;
        }
        return $payload;
    }
    /**
     * @return array<string, string>
     *
     * @since 0.1.0
     */
    private function getHeaders(Email $email): array
    {
        $headers = [];
        $tags = [];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
                continue;
            }
            if ($header instanceof MetadataHeader) {
                $metadataHeaderName = $this->normalizeMetadataHeaderName($header->getKey());
                if ($metadataHeaderName !== null) {
                    $headers[$metadataHeaderName] = $header->getValue();
                }
                continue;
            }
            $headerName = is_string($name) && $name !== '' ? $name : $header->getName();
            if (!$this->isAllowedCustomHeader($headerName)) {
                continue;
            }
            $headers[$header->getName()] = $header->getBodyAsString();
        }
        if ($tags !== []) {
            $headers['X-Tags'] = implode(',', $tags);
        }
        return $headers;
    }
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function formatPrimaryRecipients(Email $email, Envelope $envelope): array
    {
        if ($email->getTo() !== []) {
            return array_map(static fn(Address $address): string => $address->getAddress(), $email->getTo());
        }
        return array_map(static fn(Address $address): string => $address->getAddress(), $envelope->getRecipients());
    }
    /**
     * @return array{address: string, name: string}|string
     *
     * @since 0.1.0
     */
    private function formatSender(Email $email, Envelope $envelope): array|string
    {
        $fromAddresses = $email->getFrom();
        $from = reset($fromAddresses);
        if ($from instanceof Address) {
            return $this->formatAddress($from);
        }
        return $envelope->getSender()->getAddress();
    }
    /**
     * @return array{address: string, name: string}|string
     *
     * @since 0.1.0
     */
    private function formatAddress(Address $address): array|string
    {
        if ($address->getName() === '' || $address->getName() === '0') {
            return $address->getAddress();
        }
        return ['address' => $address->getAddress(), 'name' => $address->getName()];
    }
    /**
     * @return list<array<string, string>>
     *
     * @since 0.1.0
     */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $dataPart) {
            $headers = $dataPart->getPreparedHeaders();
            $disposition = strtolower((string) ($headers->get('Content-Disposition')?->getBody() ?? 'attachment'));
            $attachment = ['content' => base64_encode($dataPart->getBody()), 'filename' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'), 'type' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream', 'disposition' => $disposition === 'inline' ? 'inline' : 'attachment'];
            if ($attachment['disposition'] === 'inline') {
                $contentId = $headers->get('Content-ID')?->getBody();
                if (is_string($contentId) && $contentId !== '') {
                    $attachment['content_id'] = trim($contentId, '<>');
                }
            }
            $attachments[] = $attachment;
        }
        return $attachments;
    }
    /**
     * @param array<string, mixed> $result
     *
     * @since 0.1.0
     */
    private function formatErrorMessage(array $result): string
    {
        $messages = [];
        foreach ($result['errors'] ?? [] as $error) {
            if (is_array($error) && is_string($error['message'] ?? null) && $error['message'] !== '') {
                $messages[] = $error['message'];
            }
        }
        if ($messages !== []) {
            return implode('; ', $messages);
        }
        foreach ($result['messages'] ?? [] as $message) {
            if (is_array($message) && is_string($message['message'] ?? null) && $message['message'] !== '') {
                $messages[] = $message['message'];
            }
        }
        if ($messages !== []) {
            return implode('; ', $messages);
        }
        return 'Unknown error';
    }
    /**
     * @since 0.1.0
     */
    private function isAllowedCustomHeader(string $name): bool
    {
        $normalized = strtolower($name);
        if (in_array($normalized, ['bcc', 'cc', 'content-transfer-encoding', 'content-type', 'date', 'dkim-signature', 'from', 'message-id', 'mime-version', 'reply-to', 'return-path', 'subject', 'to'], \true)) {
            return \false;
        }
        if (str_starts_with($normalized, 'received') || str_starts_with($normalized, 'arc-')) {
            return \false;
        }
        if (str_starts_with($normalized, 'cfbl-')) {
            return \false;
        }
        if (str_starts_with($normalized, 'x-')) {
            return \true;
        }
        if (in_array($normalized, ['feedback-id', 'tls-report-domain', 'tls-report-submitter', 'tls-required'], \true)) {
            return \false;
        }
        return in_array($normalized, self::ALLOWED_HEADERS, \true);
    }
    /**
     * @since 0.1.0
     */
    private function normalizeMetadataHeaderName(string $key): ?string
    {
        $normalized = preg_replace('/[^A-Za-z0-9\-_]+/', '-', $key);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }
        return substr('X-Metadata-' . trim($normalized, '-'), 0, 100);
    }
    /**
     * @since 0.1.0
     */
    private function getEndpoint(): string
    {
        $host = ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
        return 'https://' . $host . '/client/v4/accounts/' . rawurlencode($this->accountId) . '/email/sending/send';
    }
}

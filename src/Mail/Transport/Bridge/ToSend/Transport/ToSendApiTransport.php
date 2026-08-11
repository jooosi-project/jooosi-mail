<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\ToSend\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * toSend REST API transport.
 *
 * @link https://tosend.com/docs/api/send-email/
 *
 * @since 0.1.0
 */
final class ToSendApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'api.tosend.com';

    /** @var list<string> */
    private const DISALLOWED_HEADERS = [
        'bcc',
        'cc',
        'content-transfer-encoding',
        'content-type',
        'date',
        'dkim-signature',
        'from',
        'message-id',
        'mime-version',
        'reply-to',
        'return-path',
        'subject',
        'to',
    ];

    public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('tosend+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', $this->getEndpoint(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote toSend server.', $response, 0, $transportException);
        }

        try {
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send email via toSend: ' . esc_html($response->getContent(false)) . sprintf(' (code %d).', $statusCode), $response, 0, $exception);
        }

        if ($statusCode >= 400) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send email via toSend: ' . esc_html($this->formatErrorMessage($result)) . sprintf(' (code %d).', $statusCode), $response);
        }

        if (is_string($result['message_id'] ?? null) && $result['message_id'] !== '') {
            $sentMessage->setMessageId($result['message_id']);
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
        $payload = [
            'from' => $this->formatSender($email, $envelope),
            'to' => $this->formatAddresses($this->getRecipients($email, $envelope)),
            'subject' => (string) $email->getSubject(),
        ];

        if ($email->getCc() !== []) {
            $payload['cc'] = $this->formatAddresses($email->getCc());
        }

        if ($email->getBcc() !== []) {
            $payload['bcc'] = $this->formatAddresses($email->getBcc());
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

        $tags = $this->getTags($email);

        if ($tags !== []) {
            $payload['tags'] = $tags;
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
        $tagHeaders = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                $tagHeaders[] = $header->getValue();

                continue;
            }

            if ($header instanceof MetadataHeader) {
                continue;
            }

            $headerName = is_string($name) && $name !== '' ? $name : $header->getName();

            if (! $this->isAllowedCustomHeader($headerName)) {
                continue;
            }

            $headers[$header->getName()] = $header->getBodyAsString();
        }

        if ($tagHeaders !== []) {
            $headers['X-Tags'] = implode(',', $tagHeaders);
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     *
     * @since 0.1.0
     */
    private function getTags(Email $email): array
    {
        $tags = [];

        foreach ($email->getHeaders()->all() as $header) {
            if (! $header instanceof MetadataHeader) {
                continue;
            }

            $key = $this->normalizeTagKey($header->getKey());

            if ($key === null) {
                continue;
            }

            $tags[$key] = substr((string) $header->getValue(), 0, 256);
        }

        return $tags;
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<array<string, string>>
     *
     * @since 0.1.0
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(fn (Address $address): array => $this->formatAddress($address), $addresses);
    }

    /**
     * @return array<string, string>
     *
     * @since 0.1.0
     */
    private function formatSender(Email $email, Envelope $envelope): array
    {
        $fromAddresses = $email->getFrom();
        $from = reset($fromAddresses);

        if ($from instanceof Address) {
            return $this->formatAddress($from);
        }

        return $this->formatAddress($envelope->getSender());
    }

    /**
     * @return array<string, string>
     *
     * @since 0.1.0
     */
    private function formatAddress(Address $address): array
    {
        $formatted = [
            'email' => $address->getAddress(),
        ];

        if ($address->getName() !== '' && $address->getName() !== '0') {
            $formatted['name'] = $address->getName();
        }

        return $formatted;
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

            $attachments[] = [
                'content' => base64_encode($dataPart->getBody()),
                'name' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'),
                'type' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream',
            ];
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

        if (is_string($result['message'] ?? null) && trim($result['message']) !== '') {
            $messages[] = trim($result['message']);
        }

        $messages = [
            ...$messages,
            ...$this->collectErrorMessages($result['errors'] ?? []),
        ];
        $messages = array_values(array_unique(array_filter($messages, static fn (string $message): bool => $message !== '')));

        if ($messages !== []) {
            return implode('; ', $messages);
        }

        if (is_string($result['error_type'] ?? null) && trim($result['error_type']) !== '') {
            return trim($result['error_type']);
        }

        return 'Unknown error';
    }

    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function collectErrorMessages(mixed $value): array
    {
        if (! is_array($value)) {
            return is_scalar($value) && trim((string) $value) !== '' ? [trim((string) $value)] : [];
        }

        $messages = [];

        foreach ($value as $entry) {
            $messages = [...$messages, ...$this->collectErrorMessages($entry)];
        }

        return $messages;
    }

    /**
     * @since 0.1.0
     */
    private function isAllowedCustomHeader(string $name): bool
    {
        $normalized = strtolower($name);

        if (in_array($normalized, self::DISALLOWED_HEADERS, true)) {
            return false;
        }

        if (str_starts_with($normalized, 'received') || str_starts_with($normalized, 'arc-')) {
            return false;
        }

        return true;
    }

    /**
     * @since 0.1.0
     */
    private function normalizeTagKey(string $key): ?string
    {
        $normalized = preg_replace('/[^A-Za-z0-9\-_]+/', '_', $key);

        if (! is_string($normalized) || trim($normalized, '_-') === '') {
            return null;
        }

        return substr(trim($normalized, '_-'), 0, 128);
    }

    /**
     * @since 0.1.0
     */
    private function getEndpoint(): string
    {
        $host = ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);

        return 'https://' . $host . '/v2/emails';
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\PostShiba\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * PostShiba REST API transport.
 *
 * @link https://www.postshiba.com/docs/send-with-http
 *
 * @since 1.0.13
 */
final class PostShibaApiTransport extends AbstractApiTransport
{
    private const HOST = 'app.postshiba.com';

    private const MAX_RECIPIENTS = 50;

    private const MAX_ATTACHMENTS = 10;

    private const MAX_ATTACHMENT_BYTES = 18 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_ATTACHMENT_EXTENSIONS = [
        'avif', 'bmp', 'csv', 'doc', 'docx', 'epub', 'gif', 'heic', 'heif', 'ics', 'jpg', 'odt', 'odp', 'ods',
        'pdf', 'png', 'ppt', 'pptx', 'rtf', 'svg', 'tiff', 'txt', 'vcf', 'webp', 'xls', 'xlsx', 'zip',
    ];

    /** @var list<string> */
    private const RESERVED_HEADERS = [
        'authentication-results',
        'bcc',
        'cc',
        'date',
        'dkim-signature',
        'from',
        'mime-version',
        'received',
        'reply-to',
        'resent-date',
        'resent-from',
        'resent-message-id',
        'resent-sender',
        'resent-to',
        'return-path',
        'sender',
        'subject',
        'to',
        'x-tenant-id',
    ];

    /**
     * @since 1.0.13
     */
    public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $clusterId = null,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    /**
     * @since 1.0.13
     */
    public function __toString(): string
    {
        return 'postshiba+api://' . $this->getEndpoint();
    }

    /**
     * @since 1.0.13
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($this->clusterId !== null && $this->clusterId !== '') {
            $headers['X-Capsule-Cluster-Id'] = $this->clusterId;
        }

        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/v1/emails', [
            'headers' => $headers,
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the PostShiba API.', $response, 0, $exception);
        }

        $result = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send email via PostShiba: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }

        if (is_array($result) && is_string($result['message_id'] ?? null) && $result['message_id'] !== '') {
            $sentMessage->setMessageId($result['message_id']);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     *
     * @since 1.0.13
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);

        if ($recipients === []) {
            throw new InvalidArgumentException('PostShiba requires at least one recipient.');
        }

        $recipientCount = count($recipients) + count($email->getCc()) + count($email->getBcc());

        if ($recipientCount > self::MAX_RECIPIENTS) {
            throw new InvalidArgumentException('PostShiba supports at most 50 recipients across To, Cc, and Bcc.');
        }

        $payload = [
            'from' => $envelope->getSender()->toString(),
            'to' => array_map(static fn (Address $address): string => $address->toString(), $recipients),
            'subject' => (string) $email->getSubject(),
        ];

        if ($email->getCc() !== []) {
            $payload['cc'] = array_map(static fn (Address $address): string => $address->toString(), $email->getCc());
        }

        if ($email->getBcc() !== []) {
            $payload['bcc'] = array_map(static fn (Address $address): string => $address->toString(), $email->getBcc());
        }

        if (count($email->getReplyTo()) > 1) {
            throw new InvalidArgumentException('PostShiba supports one Reply-To address.');
        }

        if ($email->getReplyTo() !== []) {
            $payload['reply_to'] = $email->getReplyTo()[0]->toString();
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

        $headers = [];
        $uniqueArgs = [];
        $tags = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if ($header instanceof MetadataHeader) {
                $uniqueArgs[$header->getKey()] = $header->getBodyAsString();

                continue;
            }

            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();

                continue;
            }

            $headerName = is_string($name) && $name !== '' ? $name : $header->getName();
            $normalizedHeaderName = strtolower($headerName);

            if ($this->isReservedHeader($normalizedHeaderName)) {
                continue;
            }

            $headers[$header->getName()] = $header->getBodyAsString();
        }

        if (count($tags) > 1) {
            throw new InvalidArgumentException('PostShiba supports one email category.');
        }

        if ($tags !== []) {
            $headers['X-Category'] = $tags[0];
        }

        if (count($uniqueArgs) > 32) {
            throw new InvalidArgumentException('PostShiba supports at most 32 unique arguments per email.');
        }

        foreach ($uniqueArgs as $key => $value) {
            if (strlen($key) > 64 || strlen($value) > 256) {
                throw new InvalidArgumentException('PostShiba unique argument keys are limited to 64 bytes and values to 256 bytes.');
            }
        }

        if ($headers !== []) {
            $payload['headers'] = $headers;
        }

        if ($uniqueArgs !== []) {
            $payload['unique_args'] = $uniqueArgs;
        }

        return $payload;
    }

    /**
     * @return list<array{filename: string, content_type: string, content: string}>
     *
     * @since 1.0.13
     */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        $totalBytes = 0;

        if (count($email->getAttachments()) > self::MAX_ATTACHMENTS) {
            throw new InvalidArgumentException('PostShiba accepts at most 10 attachments per email.');
        }

        foreach ($email->getAttachments() as $attachment) {
            $preparedHeaders = $attachment->getPreparedHeaders();
            $disposition = strtolower((string) ($preparedHeaders->get('Content-Disposition')?->getBody() ?? 'attachment'));

            if (str_starts_with($disposition, 'inline') || $preparedHeaders->has('Content-ID')) {
                throw new InvalidArgumentException('PostShiba REST API does not support inline CID attachments; use the SMTP transport.');
            }

            $filename = (string) ($preparedHeaders->getHeaderParameter('Content-Disposition', 'filename') ?? '');
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($filename === '' || ! in_array($extension, self::ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
                throw new InvalidArgumentException('PostShiba requires attachment filenames with a supported extension.');
            }

            $content = $attachment->getBody();
            $totalBytes += strlen($content);

            if ($totalBytes > self::MAX_ATTACHMENT_BYTES) {
                throw new InvalidArgumentException('PostShiba REST API accepts up to 18 MB of attachments per email.');
            }

            $attachments[] = [
                'filename' => $filename,
                'content_type' => $preparedHeaders->get('Content-Type')?->getBody() ?? 'application/octet-stream',
                'content' => base64_encode($content),
            ];
        }

        return $attachments;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @since 1.0.13
     */
    private function formatErrorMessage(array $result): string
    {
        $message = '';

        foreach (['message', 'detail', 'error'] as $key) {
            $value = $result[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $message = trim($value);
                break;
            }
        }

        $field = is_string($result['field'] ?? null) ? trim($result['field']) : '';

        return $field !== '' && $message !== '' ? $field . ': ' . $message : $message;
    }

    /**
     * @since 1.0.13
     */
    private function isReservedHeader(string $name): bool
    {
        if (in_array($name, self::RESERVED_HEADERS, true) || str_starts_with($name, 'content-') || str_starts_with($name, 'resent-')) {
            return true;
        }

        return str_starts_with($name, 'x-capsule-') && ! in_array($name, ['x-capsule-ip', 'x-capsule-pool'], true);
    }

    /**
     * @since 1.0.13
     */
    private function getEndpoint(): string
    {
        return $this->host ?: self::HOST;
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\MailKite\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\HttpTransportException;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\TransportException;
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
 * MailKite REST API transport.
 *
 * @since 0.1.0
 */
final class MailKiteApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.mailkite.dev';
    /** @var list<string> */
    private const RESERVED_HEADERS = ['bcc', 'cc', 'content-disposition', 'content-id', 'content-transfer-encoding', 'content-type', 'date', 'dkim-signature', 'from', 'in-reply-to', 'message-id', 'mime-version', 'received', 'reply-to', 'return-path', 'sender', 'subject', 'to'];
    public function __construct(
        #[SensitiveParameter]
        private readonly string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }
    public function __toString(): string
    {
        return sprintf('mailkite+api://%s', $this->getEndpoint());
    }
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v1/send', ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the MailKite API.', $response, 0, $exception);
        }
        try {
            $result = $response->toArray(\false);
        } catch (DecodingExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send email via MailKite: ' . esc_html($response->getContent(\false)) . sprintf(' (code %d).', $statusCode), $response, 0, $exception);
        } catch (TransportExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the MailKite API.', $response, 0, $exception);
        }
        if (!in_array($statusCode, [200, 202], \true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send email via MailKite: ' . esc_html($this->formatErrorMessage($result)) . sprintf(' (code %d).', $statusCode), $response);
        }
        if (is_string($result['id'] ?? null) && $result['id'] !== '') {
            $sentMessage->setMessageId($result['id']);
        }
        return $response;
    }
    /** @return array<string, mixed> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = ['from' => $envelope->getSender()->toString(), 'to' => array_map(static fn(Address $address): string => $address->toString(), $this->getRecipients($email, $envelope)), 'subject' => (string) $email->getSubject()];
        if ($email->getCc() !== []) {
            $payload['cc'] = array_map(static fn(Address $address): string => $address->toString(), $email->getCc());
        }
        if ($email->getBcc() !== []) {
            $payload['bcc'] = array_map(static fn(Address $address): string => $address->toString(), $email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $replyTo = $email->getReplyTo();
            $firstReplyTo = reset($replyTo);
            if ($firstReplyTo instanceof Address && count($replyTo) === 1) {
                $payload['replyTo'] = $firstReplyTo->toString();
            }
        }
        if (($inReplyTo = $email->getHeaders()->getHeaderBody('In-Reply-To')) !== null) {
            $payload['inReplyTo'] = $inReplyTo;
        }
        if (count($email->getReplyTo()) > 1) {
            $payload['headers']['Reply-To'] = implode(', ', array_map(static fn(Address $address): string => $address->toString(), $email->getReplyTo()));
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
        $metadata = [];
        $tags = [];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
                continue;
            }
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
                continue;
            }
            $headerName = is_string($name) && $name !== '' ? $name : $header->getName();
            if (in_array(strtolower($headerName), self::RESERVED_HEADERS, \true)) {
                continue;
            }
            $headers[$header->getName()] = $header->getBodyAsString();
        }
        if ($tags !== []) {
            $headers['X-Tag'] = implode(',', $tags);
        }
        if ($headers !== []) {
            $payload['headers'] = array_merge($payload['headers'] ?? [], $headers);
        }
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }
        return $payload;
    }
    /** @return list<array{filename: string, content: string, contentType: string}> */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = strtolower((string) ($headers->get('Content-Disposition')?->getBody() ?? 'attachment'));
            if (str_starts_with($disposition, 'inline') || $headers->has('Content-ID')) {
                throw new TransportException('MailKite API does not support inline CID attachments; use the MailKite SMTP transport.');
            }
            $attachments[] = ['filename' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'), 'content' => base64_encode($attachment->getBody()), 'contentType' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream'];
        }
        return $attachments;
    }
    /** @param array<string, mixed> $result */
    private function formatErrorMessage(array $result): string
    {
        foreach (['message', 'error', 'detail'] as $key) {
            if (is_string($result[$key] ?? null) && trim($result[$key]) !== '') {
                return trim($result[$key]);
            }
            if (is_array($result[$key] ?? null) && is_string($result[$key]['message'] ?? null) && trim($result[$key]['message']) !== '') {
                return trim($result[$key]['message']);
            }
        }
        return 'Unknown error';
    }
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

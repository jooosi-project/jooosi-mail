<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Plunk\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\HttpTransportException;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\InvalidArgumentException;
use JooosiMailDeps\Symfony\Component\Mailer\Header\MetadataHeader;
use JooosiMailDeps\Symfony\Component\Mailer\Header\TagHeader;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractApiTransport;
use JooosiMailDeps\Symfony\Component\Mime\Address;
use JooosiMailDeps\Symfony\Component\Mime\Email;
use JooosiMailDeps\Symfony\Component\Mime\Part\DataPart;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * Plunk transactional email API transport.
 *
 * @since 1.0.13
 */
final class PlunkApiTransport extends AbstractApiTransport
{
    private const HOST = 'next-api.useplunk.com';
    /** @var list<string> */
    private const RESERVED_HEADERS = ['bcc', 'cc', 'content-disposition', 'content-id', 'content-transfer-encoding', 'content-type', 'date', 'dkim-signature', 'from', 'in-reply-to', 'message-id', 'mime-version', 'received', 'reply-to', 'return-path', 'sender', 'subject', 'to'];
    /** @since 1.0.13 */
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
    /** @since 1.0.13 */
    public function __toString(): string
    {
        return 'plunk+api://' . $this->getEndpoint();
    }
    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        if ($email->getCc() !== [] || $email->getBcc() !== []) {
            throw new InvalidArgumentException('Plunk API does not support CC or BCC recipients.');
        }
        $recipients = array_map(static fn(Address $recipient): string => $recipient->getAddress(), $this->getRecipients($email, $envelope));
        if ($recipients === []) {
            throw new InvalidArgumentException('Plunk requires at least one To recipient.');
        }
        if (count($email->getReplyTo()) > 1) {
            throw new InvalidArgumentException('Plunk supports only one Reply-To address.');
        }
        $payload = ['to' => count($recipients) === 1 ? $recipients[0] : $recipients, 'subject' => $email->getSubject(), 'body' => $email->getHtmlBody() ?: $email->getTextBody() ?? '', 'from' => $envelope->getSender()->toString()];
        if ($email->getReplyTo() !== []) {
            $payload['reply'] = $email->getReplyTo()[0]->getAddress();
        }
        $headers = [];
        $metadata = [];
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
                continue;
            }
            if ($header instanceof TagHeader) {
                throw new InvalidArgumentException('Plunk does not support tags.');
            }
            if (!in_array(strtolower($header->getName()), self::RESERVED_HEADERS, \true)) {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }
        if ($headers !== []) {
            $payload['headers'] = $headers;
        }
        if ($metadata !== []) {
            $payload['data'] = $metadata;
        }
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }
            $attachmentHeaders = $attachment->getPreparedHeaders();
            $disposition = strtolower((string) ($attachmentHeaders->get('Content-Disposition')?->getBodyAsString() ?? 'attachment'));
            if (str_starts_with($disposition, 'inline') || $attachment->hasContentId()) {
                throw new InvalidArgumentException('Plunk does not support inline CID attachments.');
            }
            $attachments[] = ['filename' => $attachment->getFilename() ?: 'attachment', 'content' => base64_encode($attachment->getBody()), 'contentType' => $attachment->getContentType()];
        }
        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }
        return $payload;
    }
    /**
     * @since 1.0.13
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v1/send', ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(\false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the Plunk API.', $response, 0, $exception);
        }
        $result = json_decode($body, \true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through Plunk: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }
        if (is_array($result)) {
            if (($result['success'] ?? \true) === \false) {
                throw new HttpTransportException('Plunk did not accept the email: ' . $this->formatErrorMessage($result), $response);
            }
            foreach (is_array($result['data']['emails'] ?? null) ? $result['data']['emails'] : [] as $emailResult) {
                if (is_array($emailResult) && strtolower((string) ($emailResult['status'] ?? '')) === 'throttled') {
                    throw new HttpTransportException('Plunk skipped the recipient because the email was throttled.', $response);
                }
            }
            $emailResult = $result['data']['emails'][0] ?? null;
            $messageId = is_array($emailResult) ? $emailResult['email'] ?? $emailResult['id'] ?? $emailResult['emailId'] ?? null : null;
            $messageId ??= $result['id'] ?? $result['emailId'] ?? null;
            if (is_string($messageId) && $messageId !== '') {
                $sentMessage->setMessageId($messageId);
            }
        }
        return $response;
    }
    /**
     * @param array<string, mixed> $result
     *
     * @since 1.0.13
     */
    private function formatErrorMessage(array $result): string
    {
        foreach (['message', 'error', 'detail', 'reason', 'msg'] as $key) {
            $value = $result[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_array($value) && is_string($value['message'] ?? null) && trim($value['message']) !== '') {
                return trim($value['message']);
            }
        }
        return 'Unknown error';
    }
    /**
     * @since 1.0.13
     */
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

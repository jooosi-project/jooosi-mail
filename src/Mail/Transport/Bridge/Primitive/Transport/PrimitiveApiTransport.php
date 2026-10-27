<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Primitive\Transport;

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
use JooosiMailDeps\Symfony\Component\Mime\Email;
use JooosiMailDeps\Symfony\Component\Mime\Part\DataPart;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * Primitive transactional email API transport.
 *
 * @since 1.0.13
 */
final class PrimitiveApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.primitive.dev';
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
        return 'primitive+api://' . $this->getEndpoint();
    }
    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);
        if ($recipients === []) {
            throw new InvalidArgumentException('Primitive requires at least one To recipient.');
        }
        if (count($recipients) > 1) {
            throw new InvalidArgumentException('Primitive supports one To recipient per request.');
        }
        if ($email->getCc() !== [] || $email->getBcc() !== []) {
            throw new InvalidArgumentException('Primitive does not support CC or BCC recipients.');
        }
        if ($email->getReplyTo() !== []) {
            throw new InvalidArgumentException('Primitive does not support a Reply-To address.');
        }
        if (($email->getHtmlBody() === null || $email->getHtmlBody() === '') && ($email->getTextBody() === null || $email->getTextBody() === '')) {
            throw new InvalidArgumentException('Primitive requires an HTML or plain-text email body.');
        }
        $payload = ['from' => $envelope->getSender()->toString(), 'to' => $recipients[0]->getAddress(), 'subject' => $email->getSubject()];
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['body_html'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['body_text'] = $email->getTextBody();
        }
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader || $header instanceof MetadataHeader) {
                throw new InvalidArgumentException('Primitive does not support tags or metadata.');
            }
            if (!in_array(strtolower($header->getName()), self::RESERVED_HEADERS, \true)) {
                throw new InvalidArgumentException(sprintf('Primitive does not support the custom email header "%s".', $header->getName()));
            }
        }
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }
            $item = ['filename' => $attachment->getFilename() ?: 'attachment', 'content_type' => $attachment->getContentType(), 'content_base64' => base64_encode($attachment->getBody())];
            if ($attachment->hasContentId()) {
                $item['content_id'] = $attachment->getContentId();
            }
            $attachments[] = $item;
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
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v1/send-mail', ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(\false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the Primitive API.', $response, 0, $exception);
        }
        $result = json_decode($body, \true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through Primitive: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }
        if (is_array($result) && is_string($result['id'] ?? null) && $result['id'] !== '') {
            $sentMessage->setMessageId($result['id']);
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

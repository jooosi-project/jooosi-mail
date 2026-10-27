<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Helo\Transport;

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
 * Helo transactional email API transport.
 *
 * @since 1.0.13
 */
final class HeloApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.helohq.com';
    /** @var list<string> */
    private const RESERVED_HEADERS = ['bcc', 'cc', 'content-disposition', 'content-id', 'content-transfer-encoding', 'content-type', 'date', 'dkim-signature', 'from', 'in-reply-to', 'message-id', 'mime-version', 'received', 'reply-to', 'return-path', 'sender', 'subject', 'to'];
    /** @since 1.0.13 */
    public function __construct(
        #[SensitiveParameter]
        private readonly string $apiKey,
        private readonly ?string $channelId = null,
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
        return 'helo+api://' . $this->getEndpoint();
    }
    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);
        if ($recipients === []) {
            throw new InvalidArgumentException('Helo requires at least one To recipient.');
        }
        $recipientCount = count($recipients) + count($email->getCc()) + count($email->getBcc());
        if ($recipientCount > 50) {
            throw new InvalidArgumentException('Helo supports at most 50 recipients per request.');
        }
        if (count($email->getReplyTo()) > 1) {
            throw new InvalidArgumentException('Helo supports one Reply-To address per request.');
        }
        if (strlen($email->getSubject()) > 256) {
            throw new InvalidArgumentException('Helo subjects cannot exceed 256 characters.');
        }
        $payload = ['from' => $this->formatNamedAddress($envelope->getSender()), 'to' => array_map(fn(Address $address): array => $this->formatNamedAddress($address), $recipients), 'subject' => $email->getSubject()];
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['html'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['text'] = $email->getTextBody();
        }
        if ($email->getCc() !== []) {
            $payload['cc'] = $this->formatAddresses($email->getCc());
        }
        if ($email->getBcc() !== []) {
            $payload['bcc'] = $this->formatAddresses($email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $email->getReplyTo()[0]->getAddress();
        }
        $headers = [];
        $tags = [];
        $metadata = [];
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $tag = $header->getValue();
                if ($tag === '' || strlen($tag) > 100) {
                    throw new InvalidArgumentException('Helo tags must contain 1 to 100 characters.');
                }
                $tags[] = $tag;
                continue;
            }
            if ($header instanceof MetadataHeader) {
                $key = $header->getKey();
                $value = $header->getValue();
                if (strlen($key) > 50 || strlen($value) > 100) {
                    throw new InvalidArgumentException('Helo metadata keys cannot exceed 50 characters and values cannot exceed 100 characters.');
                }
                $metadata[$key] = $value;
                continue;
            }
            if (!in_array(strtolower($header->getName()), self::RESERVED_HEADERS, \true)) {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }
        if ($headers !== []) {
            $payload['headers'] = $headers;
        }
        if (count($tags) > 5) {
            throw new InvalidArgumentException('Helo supports at most 5 tags per email.');
        }
        if ($tags !== []) {
            $payload['tags'] = $tags;
        }
        if (count($metadata) > 10) {
            throw new InvalidArgumentException('Helo supports at most 10 metadata fields per email.');
        }
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }
            $attachmentHeaders = $attachment->getPreparedHeaders();
            $disposition = strtolower((string) ($attachmentHeaders->get('Content-Disposition')?->getBodyAsString() ?? 'attachment'));
            $item = ['filename' => $attachment->getFilename() ?: 'attachment', 'content' => base64_encode($attachment->getBody()), 'contentType' => $attachment->getContentType()];
            if ($attachment->hasContentId()) {
                $item['contentId'] = $attachment->getContentId();
            }
            if (str_starts_with($disposition, 'inline')) {
                $item['disposition'] = 'inline';
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
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        if ($this->channelId !== null && $this->channelId !== '') {
            $headers['X-Helo-Channel-Id'] = $this->channelId;
        }
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/send/transactional', ['headers' => $headers, 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(\false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the Helo API.', $response, 0, $exception);
        }
        $result = json_decode($body, \true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through Helo: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }
        if (is_array($result)) {
            if (strtolower((string) ($result['status'] ?? '')) === 'failed') {
                $reason = $this->formatErrorMessage($result);
                if ($reason === 'Unknown error' && is_string($result['errorCode'] ?? null)) {
                    $reason = $result['errorCode'];
                }
                throw new HttpTransportException(sprintf('Helo did not accept the email: %s', $reason), $response);
            }
            if (is_string($result['id'] ?? null) && $result['id'] !== '') {
                $sentMessage->setMessageId($result['id']);
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
     * @param list<Address> $addresses
     *
     * @return list<string>
     *
     * @since 1.0.13
     */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_map(static fn(Address $address): string => $address->getAddress(), $addresses));
    }
    /**
     * @return array{email: string, name?: string}
     *
     * @since 1.0.13
     */
    private function formatNamedAddress(Address $address): array
    {
        $formatted = ['email' => $address->getAddress()];
        if ($address->getName() !== '') {
            $formatted['name'] = $address->getName();
        }
        return $formatted;
    }
    /**
     * @since 1.0.13
     */
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

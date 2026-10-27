<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\JetEmail\Transport;

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
 * JetEmail transactional email API transport.
 *
 * @since 1.0.13
 */
final class JetEmailApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.jetemail.com';
    /** @var list<string> */
    private const RESERVED_HEADERS = ['bcc', 'cc', 'content-disposition', 'content-id', 'content-transfer-encoding', 'content-type', 'date', 'dkim-signature', 'from', 'in-reply-to', 'message-id', 'mime-version', 'received', 'reply-to', 'return-path', 'sender', 'subject', 'to'];
    /** @since 1.0.13 */
    public function __construct(
        #[SensitiveParameter]
        private readonly string $apiKey,
        private readonly string $region = 'global',
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
        return 'jetemail+api://' . $this->getEndpoint();
    }
    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        if (count($email->getReplyTo()) > 1) {
            throw new InvalidArgumentException('JetEmail supports only one Reply-To address.');
        }
        $recipientCount = count($this->getRecipients($email, $envelope)) + count($email->getCc()) + count($email->getBcc());
        if ($recipientCount > 50) {
            throw new InvalidArgumentException('JetEmail supports at most 50 recipients across To, Cc, and Bcc.');
        }
        if ($envelope->getSender()->getName() === '') {
            throw new InvalidArgumentException('JetEmail requires a display name in the From address.');
        }
        $payload = ['from' => $envelope->getSender()->toString(), 'to' => $this->formatAddresses($this->getRecipients($email, $envelope)), 'subject' => $email->getSubject()];
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
            $payload['reply_to'] = $email->getReplyTo()[0]->getAddress();
        }
        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader || $header instanceof MetadataHeader) {
                throw new InvalidArgumentException('JetEmail does not support tags or metadata.');
            }
            if (!in_array(strtolower($header->getName()), self::RESERVED_HEADERS, \true)) {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }
        if ($headers !== []) {
            $payload['headers'] = $headers;
        }
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }
            $item = ['filename' => $attachment->getFilename() ?: 'attachment', 'content' => base64_encode($attachment->getBody()), 'content_type' => $attachment->getContentType()];
            if ($attachment->hasContentId()) {
                $item['content_id'] = $attachment->getContentId();
            }
            $attachments[] = $item;
        }
        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }
        if ($this->region === 'eu') {
            $payload['eu'] = \true;
        }
        return $payload;
    }
    /**
     * @since 1.0.13
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/email', ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(\false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the JetEmail API.', $response, 0, $exception);
        }
        $result = json_decode($body, \true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through JetEmail: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
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
     * @since 1.0.13
     */
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

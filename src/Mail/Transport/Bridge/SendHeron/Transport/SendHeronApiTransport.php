<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\SendHeron\Transport;

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
 * SendHeron transactional email API transport.
 *
 * @since 1.0.13
 */
final class SendHeronApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.sendheron.com';
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
        return 'sendheron+api://' . $this->getEndpoint();
    }
    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->formatAddresses($this->getRecipients($email, $envelope));
        if ($recipients === []) {
            throw new InvalidArgumentException('SendHeron requires at least one To recipient.');
        }
        if (count($recipients) > 1) {
            throw new InvalidArgumentException('SendHeron supports one recipient per request.');
        }
        if (count($email->getReplyTo()) > 1) {
            throw new InvalidArgumentException('SendHeron supports only one Reply-To address.');
        }
        if (count($email->getCc()) > 50 || count($email->getBcc()) > 50) {
            throw new InvalidArgumentException('SendHeron supports at most 50 Cc and 50 Bcc recipients.');
        }
        $sender = $envelope->getSender();
        $payload = ['to' => $recipients[0], 'subject' => $email->getSubject(), 'html' => $email->getHtmlBody() ?: '<pre>' . htmlspecialchars($email->getTextBody() ?? '', \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '</pre>', 'from' => $sender->getAddress()];
        if ($sender->getName() !== '') {
            $payload['fromName'] = $sender->getName();
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
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader || $header instanceof MetadataHeader) {
                throw new InvalidArgumentException('SendHeron does not support tags or metadata.');
            }
            if (!in_array(strtolower($header->getName()), ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'mime-version', 'content-type', 'content-transfer-encoding', 'date', 'message-id', 'return-path', 'sender'], \true)) {
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
            $attachmentHeaders = $attachment->getPreparedHeaders();
            $disposition = strtolower((string) ($attachmentHeaders->get('Content-Disposition')?->getBodyAsString() ?? 'attachment'));
            if (str_starts_with($disposition, 'inline') || $attachment->hasContentId()) {
                throw new InvalidArgumentException('SendHeron does not support inline CID attachments.');
            }
            $attachments[] = ['filename' => $attachment->getFilename() ?: 'attachment', 'content' => base64_encode($attachment->getBody()), 'type' => $attachment->getContentType(), 'disposition' => 'attachment'];
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
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/v1/emails/send', ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(\false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the SendHeron API.', $response, 0, $exception);
        }
        $result = json_decode($body, \true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through SendHeron: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }
        if (is_array($result)) {
            if (strtolower((string) ($result['status'] ?? '')) === 'suppressed') {
                $reason = trim((string) ($result['errorMessage'] ?? ''));
                throw new HttpTransportException('SendHeron suppressed the email: ' . ($reason !== '' ? $reason : 'Unknown reason'), $response);
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
     * @since 1.0.13
     */
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

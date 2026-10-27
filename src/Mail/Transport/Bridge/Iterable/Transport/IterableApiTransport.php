<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Iterable\Transport;

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
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * Iterable transactional campaign API transport.
 *
 * @since 1.0.13
 */
final class IterableApiTransport extends AbstractApiTransport
{
    private const HOST_US = 'api.iterable.com';
    private const HOST_EU = 'api.eu.iterable.com';
    /** @var list<string> */
    private const RESERVED_HEADERS = ['bcc', 'cc', 'content-disposition', 'content-id', 'content-transfer-encoding', 'content-type', 'date', 'dkim-signature', 'from', 'in-reply-to', 'message-id', 'mime-version', 'received', 'reply-to', 'return-path', 'sender', 'subject', 'to'];
    /** @since 1.0.13 */
    public function __construct(
        #[SensitiveParameter]
        private readonly string $apiKey,
        private readonly int $campaignId,
        private readonly string $region = 'us',
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
        return 'iterable+api://' . $this->getEndpoint();
    }
    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);
        if ($recipients === []) {
            throw new InvalidArgumentException('Iterable requires at least one To recipient.');
        }
        if (count($recipients) > 1) {
            throw new InvalidArgumentException('Iterable supports one recipient per request.');
        }
        if ($email->getCc() !== [] || $email->getBcc() !== []) {
            throw new InvalidArgumentException('Iterable API does not support CC or BCC recipients.');
        }
        if ($email->getAttachments() !== []) {
            throw new InvalidArgumentException('Iterable API does not support attachments.');
        }
        if ($email->getReplyTo() !== []) {
            throw new InvalidArgumentException('Iterable campaign sends do not support a Reply-To address.');
        }
        if ($this->campaignId < 1) {
            throw new InvalidArgumentException('Iterable requires a positive transactional campaign ID.');
        }
        $dataFields = ['subject' => $email->getSubject(), 'from' => $envelope->getSender()->toString()];
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $dataFields['html'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $dataFields['text'] = $email->getTextBody();
        }
        $metadata = [];
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
                continue;
            }
            if ($header instanceof TagHeader) {
                throw new InvalidArgumentException('Iterable does not support tags.');
            }
            if (!in_array(strtolower($header->getName()), self::RESERVED_HEADERS, \true)) {
                throw new InvalidArgumentException(sprintf('Iterable does not support the custom email header "%s".', $header->getName()));
            }
        }
        $payload = ['campaignId' => $this->campaignId, 'recipientEmail' => $recipients[0]->getAddress(), 'dataFields' => $dataFields];
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }
        return $payload;
    }
    /**
     * @since 1.0.13
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/email/target', ['headers' => ['Api-Key' => $this->apiKey], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(\false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the Iterable API.', $response, 0, $exception);
        }
        $result = json_decode($body, \true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through Iterable: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
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
        $host = $this->region === 'eu' ? self::HOST_EU : self::HOST_US;
        return ($this->host ?: $host) . ($this->port === null ? '' : ':' . $this->port);
    }
}

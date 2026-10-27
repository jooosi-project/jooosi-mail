<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Lettr\Transport;

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
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Lettr transactional email API transport.
 *
 * @since 1.0.13
 */
final class LettrApiTransport extends AbstractApiTransport
{
    private const HOST = 'app.lettr.com';

    /** @var list<string> */
    private const RESERVED_HEADERS = [
        'bcc',
        'cc',
        'content-disposition',
        'content-id',
        'content-transfer-encoding',
        'content-type',
        'date',
        'dkim-signature',
        'from',
        'in-reply-to',
        'message-id',
        'mime-version',
        'received',
        'reply-to',
        'return-path',
        'sender',
        'subject',
        'to',
    ];

    /** @since 1.0.13 */
    public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    /** @since 1.0.13 */
    public function __toString(): string
    {
        return 'lettr+api://' . $this->getEndpoint();
    }

    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->formatAddresses($this->getRecipients($email, $envelope));
        if ($recipients === []) {
            throw new InvalidArgumentException('Lettr requires at least one To recipient.');
        }
        $recipientCount = count($recipients) + count($email->getCc()) + count($email->getBcc());
        if ($recipientCount > 50) {
            throw new InvalidArgumentException('Lettr supports at most 50 recipients across To, Cc, and Bcc.');
        }
        if (count($email->getReplyTo()) > 1) {
            throw new InvalidArgumentException('Lettr supports only one Reply-To address.');
        }
        foreach ([$recipients, $email->getCc(), $email->getBcc()] as $addressList) {
            foreach ($addressList as $address) {
                if ($address->getName() !== '') {
                    throw new InvalidArgumentException('Lettr does not support recipient display names.');
                }
            }
        }

        $sender = $envelope->getSender();
        $payload = [
            'from' => $sender->getAddress(),
            'to' => $recipients,
            'subject' => $email->getSubject(),
        ];
        if ($sender->getName() !== '') {
            $payload['from_name'] = $sender->getName();
        }
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
        $replyTo = $email->getReplyTo()[0] ?? null;
        if ($replyTo instanceof Address && $replyTo->getName() !== '') {
            $payload['reply_to_name'] = $replyTo->getName();
        }

        $headers = [];
        $metadata = [];
        $tags = [];
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();

                continue;
            }

            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();

                continue;
            }

            if (! in_array(strtolower($header->getName()), self::RESERVED_HEADERS, true)) {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }
        if (count($headers) > 10) {
            throw new InvalidArgumentException('Lettr supports at most 10 custom headers per email.');
        }
        if ($headers !== []) {
            $payload['headers'] = $headers;
        }
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }
        if (count($tags) > 1) {
            throw new InvalidArgumentException('Lettr supports one tag per email.');
        }
        if ($tags !== []) {
            $payload['tag'] = $tags[0];
        }

        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (! $attachment instanceof DataPart) {
                continue;
            }

            $headers = $attachment->getPreparedHeaders();
            $disposition = strtolower((string) ($headers->get('Content-Disposition')?->getBodyAsString() ?? 'attachment'));
            if (str_starts_with($disposition, 'inline') || $attachment->hasContentId()) {
                throw new InvalidArgumentException('Lettr does not support inline CID attachments.');
            }
            $attachments[] = [
                'name' => $attachment->getFilename() ?: 'attachment',
                'type' => $attachment->getContentType(),
                'data' => base64_encode($attachment->getBody()),
            ];
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
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/emails', [
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
            'json' => $this->getPayload($email, $envelope),
        ]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the Lettr API.', $response, 0, $exception);
        }

        $result = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through Lettr: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }

        if (is_array($result) && is_string($result['request_id'] ?? null) && $result['request_id'] !== '') {
            $sentMessage->setMessageId($result['request_id']);
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
        return array_values(array_map(static fn (Address $address): string => $address->getAddress(), $addresses));
    }

    /**
     * @since 1.0.13
     */
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

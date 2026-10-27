<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Lettermint\Transport;

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
 * Lettermint transactional email API transport.
 *
 * @since 1.0.13
 */
final class LettermintApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.lettermint.co';

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
        private readonly ?string $route = null,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    /** @since 1.0.13 */
    public function __toString(): string
    {
        return 'lettermint+api://' . $this->getEndpoint();
    }

    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);
        if ($recipients === []) {
            throw new InvalidArgumentException('Lettermint requires at least one To recipient.');
        }
        $recipientCount = count($recipients) + count($email->getCc()) + count($email->getBcc());
        if ($recipientCount > 50) {
            throw new InvalidArgumentException('Lettermint supports at most 50 recipients across To, Cc, and Bcc.');
        }
        if (count($email->getReplyTo()) > 1) {
            throw new InvalidArgumentException('Lettermint supports only one Reply-To address.');
        }
        $payload = [
            'from' => $envelope->getSender()->toString(),
            'to' => $this->formatAddresses($recipients),
            'subject' => $email->getSubject(),
        ];
        if ($this->route !== null && $this->route !== '') {
            $payload['route'] = $this->route;
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
        if ($headers !== []) {
            $payload['headers'] = $headers;
        }
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }
        if (count($tags) > 1) {
            throw new InvalidArgumentException('Lettermint supports one tag per email.');
        }
        if ($tags !== []) {
            $payload['tag'] = $tags[0];
        }

        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (! $attachment instanceof DataPart) {
                continue;
            }

            $item = [
                'filename' => $attachment->getFilename() ?: 'attachment',
                'content' => base64_encode($attachment->getBody()),
                'content_type' => $attachment->getContentType(),
            ];
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
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v1/send', [
            'headers' => ['X-Lettermint-Token' => $this->apiKey],
            'json' => $this->getPayload($email, $envelope),
        ]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the Lettermint API.', $response, 0, $exception);
        }

        $result = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through Lettermint: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }

        if (is_array($result) && is_string($result['message_id'] ?? null) && $result['message_id'] !== '') {
            $sentMessage->setMessageId($result['message_id']);
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

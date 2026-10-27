<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Loops\Transport;

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
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Loops transactional template API transport.
 *
 * @since 1.0.13
 */
final class LoopsApiTransport extends AbstractApiTransport
{
    private const HOST = 'app.loops.so';

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
        private readonly string $transactionalId,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    /** @since 1.0.13 */
    public function __toString(): string
    {
        return 'loops+api://' . $this->getEndpoint();
    }

    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);
        if ($recipients === []) {
            throw new InvalidArgumentException('Loops requires at least one To recipient.');
        }
        if (count($recipients) > 1) {
            throw new InvalidArgumentException('Loops supports one recipient per request.');
        }
        if ($email->getCc() !== [] || $email->getBcc() !== []) {
            throw new InvalidArgumentException('Loops does not support CC or BCC recipients.');
        }
        if ($email->getReplyTo() !== []) {
            throw new InvalidArgumentException('Loops does not support a Reply-To address.');
        }
        if ($this->transactionalId === '') {
            throw new InvalidArgumentException('Loops requires a transactional email ID.');
        }

        $dataVariables = [
            'subject' => $email->getSubject(),
            'from' => $envelope->getSender()->toString(),
        ];
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $dataVariables['html'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $dataVariables['text'] = $email->getTextBody();
        }

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $dataVariables[$header->getKey()] = $header->getValue();

                continue;
            }

            if ($header instanceof TagHeader) {
                throw new InvalidArgumentException('Loops does not support tags.');
            }

            if (! in_array(strtolower($header->getName()), self::RESERVED_HEADERS, true)) {
                throw new InvalidArgumentException(sprintf('Loops does not support the custom email header "%s".', $header->getName()));
            }
        }

        $payload = [
            'transactionalId' => $this->transactionalId,
            'email' => $recipients[0]->getAddress(),
            'dataVariables' => $dataVariables,
        ];
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (! $attachment instanceof DataPart) {
                continue;
            }

            $headers = $attachment->getPreparedHeaders();
            $disposition = strtolower((string) ($headers->get('Content-Disposition')?->getBodyAsString() ?? 'attachment'));
            if (str_starts_with($disposition, 'inline') || $attachment->hasContentId()) {
                throw new InvalidArgumentException('Loops does not support inline CID attachments.');
            }
            $attachments[] = [
                'filename' => $attachment->getFilename() ?: 'attachment',
                'contentType' => $attachment->getContentType(),
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
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/v1/transactional', [
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
            'json' => $this->getPayload($email, $envelope),
        ]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the Loops API.', $response, 0, $exception);
        }

        $result = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through Loops: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }

        if (is_array($result)) {
            $messageId = $result['id'] ?? $result['transactionalId'] ?? null;
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

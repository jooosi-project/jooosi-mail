<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Sequenzy\Transport;

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
 * Sequenzy transactional email API transport.
 *
 * @since 1.0.13
 */
final class SequenzyApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.sequenzy.com';
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
        return 'sequenzy+api://' . $this->getEndpoint();
    }
    /** @since 1.0.13 */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);
        if ($recipients === []) {
            throw new InvalidArgumentException('Sequenzy requires at least one To recipient.');
        }
        if (count($recipients) > 50) {
            throw new InvalidArgumentException('Sequenzy supports at most 50 To recipients per request.');
        }
        if (count($email->getReplyTo()) > 1) {
            throw new InvalidArgumentException('Sequenzy supports only one Reply-To address.');
        }
        if ($email->getCc() !== [] || $email->getBcc() !== []) {
            throw new InvalidArgumentException('Sequenzy does not support CC or BCC recipients.');
        }
        $payload = ['from' => $envelope->getSender()->toString(), 'to' => array_map(static fn(Address $recipient): string => $recipient->getAddress(), $recipients)];
        $replyTo = $email->getReplyTo()[0] ?? null;
        if ($replyTo !== null) {
            $payload['replyTo'] = $replyTo->getAddress();
        }
        $variables = [];
        $templateSlug = null;
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                throw new InvalidArgumentException('Sequenzy does not support tags.');
            }
            if ($header instanceof MetadataHeader) {
                $key = $header->getKey();
                $value = $header->getValue();
                if ($key === 'sequenzySlug') {
                    $templateSlug = $value;
                } elseif ($key === 'sequenzyPreview') {
                    $payload['preview'] = $value;
                } elseif ($key === 'subscriberExternalId' || $key === 'sequenzySubscriberExternalId') {
                    $payload['subscriberExternalId'] = $value;
                } else {
                    $variables[$key] = $value;
                }
                continue;
            }
            if (!in_array(strtolower($header->getName()), self::RESERVED_HEADERS, \true)) {
                throw new InvalidArgumentException(sprintf('Sequenzy does not support the custom email header "%s".', $header->getName()));
            }
        }
        if (is_string($templateSlug) && $templateSlug !== '') {
            $payload['slug'] = $templateSlug;
        } else {
            $payload['subject'] = $email->getSubject();
            $payload['body'] = $email->getHtmlBody() ?: $email->getTextBody() ?? '';
        }
        if ($variables !== []) {
            $payload['variables'] = $variables;
        }
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }
            $item = ['filename' => $attachment->getFilename() ?: 'attachment', 'content' => base64_encode($attachment->getBody()), 'contentType' => $attachment->getContentType()];
            if ($attachment->hasContentId()) {
                $item['contentId'] = $attachment->getContentId();
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
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/v1/transactional/send', ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(\false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpTransportException('Could not reach the Sequenzy API.', $response, 0, $exception);
        }
        $result = json_decode($body, \true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($result) ? $this->formatErrorMessage($result) : trim($body);
            throw new HttpTransportException(sprintf('Unable to send an email through Sequenzy: %s (HTTP %d).', $message !== '' ? $message : 'Unknown error', $statusCode), $response);
        }
        if (is_array($result)) {
            $messageId = $result['emailSendId'] ?? $result['jobId'] ?? $result['data']['emailSendId'] ?? $result['data']['jobId'] ?? null;
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

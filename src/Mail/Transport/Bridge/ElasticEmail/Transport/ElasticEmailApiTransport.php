<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\ElasticEmail\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\HttpTransportException;
use JooosiMailDeps\Symfony\Component\Mailer\Header\MetadataHeader;
use JooosiMailDeps\Symfony\Component\Mailer\Header\TagHeader;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractApiTransport;
use JooosiMailDeps\Symfony\Component\Mime\Address;
use JooosiMailDeps\Symfony\Component\Mime\Email;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * Elastic Email API transport.
 *
 * @since 0.1.0
 */
final class ElasticEmailApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'api.elasticemail.com';
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
    public function __toString(): string
    {
        return sprintf('elasticemail+api://%s', $this->getEndpoint());
    }
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v4/emails', ['json' => $this->getPayload($email, $envelope), 'headers' => ['X-ElasticEmail-ApiKey' => $this->apiKey, 'Content-Type' => 'application/json']]);
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote Elastic Email server.', $response, 0, $transportException);
        }
        if (!in_array($statusCode, [200, 201], \true)) {
            try {
                $result = $response->toArray(\false);
                $message = (string) ($result['Error'] ?? 'Unknown error');
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($message) . sprintf(' (code %d).', $statusCode), $response);
            } catch (DecodingExceptionInterface $exception) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($response->getContent(\false)) . sprintf(' (code %d).', $statusCode), $response, 0, $exception);
            }
        }
        try {
            $result = $response->toArray(\false);
            if (is_string($result['TransactionID'] ?? null) && $result['TransactionID'] !== '') {
                $sentMessage->setMessageId($result['TransactionID']);
            }
        } catch (DecodingExceptionInterface) {
        }
        return $response;
    }
    /** @return array<string, mixed> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $address = $envelope->getSender();
        $payload = ['Recipients' => $this->formatRecipients($this->getRecipients($email, $envelope)), 'Content' => ['Body' => [], 'From' => $address->getAddress(), 'Subject' => $email->getSubject()]];
        if ($address->getName() !== '' && $address->getName() !== '0') {
            $payload['Content']['FromName'] = $address->getName();
        }
        if ($email->getCc() !== []) {
            $payload['Recipients']['CC'] = $this->formatAddresses($email->getCc());
        }
        if ($email->getBcc() !== []) {
            $payload['Recipients']['BCC'] = $this->formatAddresses($email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $replyTo = reset($email->getReplyTo());
            if ($replyTo instanceof Address) {
                $payload['Content']['ReplyTo'] = $replyTo->getAddress();
                if ($replyTo->getName() !== '') {
                    $payload['Content']['ReplyToName'] = $replyTo->getName();
                }
            }
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['Content']['Body'][] = ['ContentType' => 'PlainText', 'Content' => $email->getTextBody()];
        }
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['Content']['Body'][] = ['ContentType' => 'HTML', 'Content' => $email->getHtmlBody()];
        }
        if ($email->getAttachments() !== []) {
            $payload['Content']['Attachments'] = $this->getAttachments($email);
        }
        $customHeaders = [];
        $hasTags = \false;
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array(strtolower((string) $name), ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type'], \true)) {
                continue;
            }
            if ($header instanceof TagHeader) {
                $hasTags = \true;
            } elseif ($header instanceof MetadataHeader) {
                $customHeaders[$header->getKey()] = $header->getValue();
            } else {
                $customHeaders[$header->getName()] = $header->getBodyAsString();
            }
        }
        if ($customHeaders !== []) {
            $payload['Content']['Headers'] = $customHeaders;
        }
        if ($hasTags) {
            $payload['Options'] = ['TrackOpens' => \true, 'TrackClicks' => \true];
        }
        return $payload;
    }
    /** @param list<Address> $addresses @return list<array<string, mixed>> */
    private function formatRecipients(array $addresses): array
    {
        $recipients = [];
        foreach ($addresses as $address) {
            $recipient = ['Email' => $address->getAddress()];
            if ($address->getName() !== '') {
                $recipient['Fields'] = ['name' => $address->getName()];
            }
            $recipients[] = $recipient;
        }
        return $recipients;
    }
    /** @param list<Address> $addresses @return list<string> */
    private function formatAddresses(array $addresses): array
    {
        return array_map(static fn(Address $address): string => $address->getAddress(), $addresses);
    }
    /** @return list<array<string, string>> */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $dataPart) {
            $headers = $dataPart->getPreparedHeaders();
            $attachments[] = ['BinaryContent' => base64_encode($dataPart->getBody()), 'Name' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'), 'ContentType' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream'];
        }
        return $attachments;
    }
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

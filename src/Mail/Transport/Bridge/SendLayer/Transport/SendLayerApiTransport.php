<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\SendLayer\Transport;

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
use Throwable;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * SendLayer API transport.
 *
 * @since 0.1.0
 */
final class SendLayerApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'console.sendlayer.com';
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
        return sprintf('sendlayer+api://%s', $this->getEndpoint());
    }
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/v1/email', ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
        } catch (Throwable $throwable) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote SendLayer server.', $response, 0, $throwable);
        }
        if ($statusCode !== 200) {
            $result = $response->toArray(\false);
            if (isset($result['message'])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($result['message']) . sprintf(' (code %d).', $statusCode), $response);
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send an email: ' . esc_html($response->getContent(\false)) . sprintf(' (code %d).', $statusCode), $response);
        }
        $result = $response->toArray(\false);
        $messageId = $result['messageId'] ?? $result['id'] ?? 'sendlayer-' . uniqid('', \true);
        $sentMessage->setMessageId((string) $messageId);
        return $response;
    }
    /** @return array<string, mixed> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = ['From' => $this->formatAddress($envelope->getSender()), 'To' => $this->formatAddresses($this->getRecipients($email, $envelope)), 'Subject' => $email->getSubject()];
        if ($email->getCc() !== []) {
            $payload['CC'] = $this->formatAddresses($email->getCc());
        }
        if ($email->getBcc() !== []) {
            $payload['BCC'] = $this->formatAddresses($email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $payload['ReplyTo'] = $this->formatAddresses($email->getReplyTo());
        }
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['ContentType'] = 'HTML';
            $payload['HTMLContent'] = $email->getHtmlBody();
            if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
                $payload['PlainContent'] = $email->getTextBody();
            }
        } else {
            $payload['ContentType'] = 'Plain';
            $payload['PlainContent'] = $email->getTextBody() ?: '';
        }
        if ($email->getAttachments() !== []) {
            $payload['Attachments'] = [];
            foreach ($email->getAttachments() as $attachment) {
                $payload['Attachments'][] = ['Name' => $attachment->getName(), 'Content' => base64_encode($attachment->getBody()), 'ContentType' => $attachment->getContentType()];
            }
        }
        $customHeaders = [];
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $payload['Tags'][] = $header->getValue();
            } elseif ($header instanceof MetadataHeader) {
                $customHeaders[$header->getKey()] = $header->getValue();
            } elseif (str_starts_with($header->getName(), 'X-')) {
                $customHeaders[$header->getName()] = $header->getBodyAsString();
            }
        }
        if ($customHeaders !== []) {
            $payload['Headers'] = $customHeaders;
        }
        return $payload;
    }
    /** @return array<string, string> */
    private function formatAddress(Address $address): array
    {
        $formatted = ['email' => $address->getAddress()];
        if ($address->getName() !== '' && $address->getName() !== '0') {
            $formatted['name'] = $address->getName();
        }
        return $formatted;
    }
    /** @param list<Address> $addresses @return list<array<string, string>> */
    private function formatAddresses(array $addresses): array
    {
        return array_map(fn(Address $address): array => $this->formatAddress($address), $addresses);
    }
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Smtp2go\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
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
 * SMTP2GO API transport.
 *
 * @since 0.1.0
 */
final class Smtp2goApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'api.smtp2go.com';
    /**
     * @var mixed[]
     */
    private const REGIONAL_HOSTS = ['global' => 'api.smtp2go.com', 'us' => 'us-api.smtp2go.com', 'eu' => 'eu-api.smtp2go.com', 'au' => 'au-api.smtp2go.com'];
    public function __construct(private readonly string $apiKey, private readonly ?string $region = null, ?HttpClientInterface $httpClient = null, ?EventDispatcherInterface $eventDispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }
    public function __toString(): string
    {
        return sprintf('smtp2go+api://%s', $this->getEndpoint());
    }
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v3/email/send', ['headers' => ['X-Smtp2go-Api-Key' => $this->apiKey, 'Content-Type' => 'application/json'], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
        } catch (Throwable $throwable) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote SMTP2GO server.', $response, 0, $throwable);
        }
        if ($statusCode !== 200) {
            $result = $response->toArray(\false);
            if (isset($result['data']['error'])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($result['data']['error']) . sprintf(' (code %d).', $statusCode), $response);
            }
            if (isset($result['data']['errors']) && is_array($result['data']['errors'])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . implode('; ', array_map('esc_html', $result['data']['errors'])) . sprintf(' (code %d).', $statusCode), $response);
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send an email: ' . esc_html($response->getContent(\false)) . sprintf(' (code %d).', $statusCode), $response);
        }
        $result = $response->toArray(\false);
        $messageId = $result['data']['email_id'] ?? $result['data']['id'] ?? 'smtp2go-' . uniqid('', \true);
        $sentMessage->setMessageId((string) $messageId);
        return $response;
    }
    /** @return array<string, mixed> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = ['sender' => $envelope->getSender()->getAddress(), 'to' => array_map(static fn(Address $address): string => $address->getAddress(), $this->getRecipients($email, $envelope)), 'subject' => $email->getSubject()];
        if ($email->getCc() !== []) {
            $payload['cc'] = array_map(static fn(Address $address): string => $address->getAddress(), $email->getCc());
        }
        if ($email->getBcc() !== []) {
            $payload['bcc'] = array_map(static fn(Address $address): string => $address->getAddress(), $email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $replyTo = reset($email->getReplyTo());
            if ($replyTo instanceof Address) {
                $payload['reply_to'] = $replyTo->getAddress();
            }
        }
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['html_body'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['text_body'] = $email->getTextBody();
        }
        if ($email->getAttachments() !== []) {
            $payload['attachments'] = [];
            foreach ($email->getAttachments() as $attachment) {
                $payload['attachments'][] = ['filename' => $attachment->getName(), 'fileblob' => base64_encode($attachment->getBody()), 'mimetype' => $attachment->getContentType()];
            }
        }
        $customHeaders = [];
        $tags = [];
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
            } elseif ($header instanceof MetadataHeader) {
                $customHeaders['X-' . $header->getKey()] = $header->getValue();
            } elseif (str_starts_with($header->getName(), 'X-')) {
                $customHeaders[$header->getName()] = $header->getBodyAsString();
            }
        }
        if ($customHeaders !== []) {
            $payload['custom_headers'] = $customHeaders;
        }
        if ($tags !== []) {
            $payload['custom_headers']['X-Tags'] = implode(',', $tags);
        }
        return $payload;
    }
    private function getEndpoint(): string
    {
        if ($this->host !== null && $this->host !== 'default') {
            return $this->host . ($this->port === null ? '' : ':' . $this->port);
        }
        $host = self::REGIONAL_HOSTS[$this->region] ?? self::HOST;
        return $host . ($this->port === null ? '' : ':' . $this->port);
    }
}

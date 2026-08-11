<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\SendPulse\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

/**
 * SendPulse API transport.
 *
 * @since 0.1.0
 */
final class SendPulseApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'api.sendpulse.com';

    private ?string $accessToken = null;

    private ?int $tokenExpiry = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('sendpulse+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $this->ensureValidToken();
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/smtp/emails', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (Throwable $throwable) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote SendPulse server.', $response, 0, $throwable);
        }

        if ($statusCode !== 200) {
            $result = $response->toArray(false);

            if (isset($result['message'])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($result['message']) . sprintf(' (code %d).', $statusCode), $response);
            }

            if (isset($result['error_description'])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($result['error_description']) . sprintf(' (code %d).', $statusCode), $response);
            }

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send an email: ' . esc_html($response->getContent(false)) . sprintf(' (code %d).', $statusCode), $response);
        }

        $result = $response->toArray(false);
        $messageId = $result['result']['id'] ?? $result['id'] ?? ('sendpulse-' . uniqid('', true));
        $sentMessage->setMessageId((string) $messageId);

        return $response;
    }

    /** @return array<string, mixed> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'email' => [
                'from' => [
                    'name' => $envelope->getSender()->getName() ?: $envelope->getSender()->getAddress(),
                    'email' => $envelope->getSender()->getAddress(),
                ],
                'to' => array_map(static fn (Address $address): array => ['name' => $address->getName() ?: $address->getAddress(), 'email' => $address->getAddress()], $this->getRecipients($email, $envelope)),
                'subject' => $email->getSubject(),
            ],
        ];

        if ($email->getCc() !== []) {
            $payload['email']['cc'] = array_map(static fn (Address $address): array => ['name' => $address->getName() ?: $address->getAddress(), 'email' => $address->getAddress()], $email->getCc());
        }

        if ($email->getBcc() !== []) {
            $payload['email']['bcc'] = array_map(static fn (Address $address): array => ['name' => $address->getName() ?: $address->getAddress(), 'email' => $address->getAddress()], $email->getBcc());
        }

        if ($email->getReplyTo() !== []) {
            $replyTo = reset($email->getReplyTo());

            if ($replyTo instanceof Address) {
                $payload['email']['reply_to'] = ['name' => $replyTo->getName() ?: $replyTo->getAddress(), 'email' => $replyTo->getAddress()];
            }
        }

        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['email']['html'] = base64_encode($email->getHtmlBody());
        }

        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['email']['text'] = $email->getTextBody();
        }

        if ($email->getAttachments() !== []) {
            $payload['email']['attachments'] = [];

            foreach ($email->getAttachments() as $attachment) {
                $payload['email']['attachments'][] = [
                    'name' => $attachment->getName(),
                    'content' => base64_encode($attachment->getBody()),
                    'type' => $attachment->getContentType(),
                ];
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
            $payload['email']['headers'] = $customHeaders;
        }

        if ($tags !== []) {
            $payload['email']['tags'] = $tags;
        }

        return $payload;
    }

    private function ensureValidToken(): void
    {
        if ($this->accessToken !== null && $this->tokenExpiry !== null && time() < $this->tokenExpiry) {
            return;
        }

        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/oauth/access_token', [
            'json' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to authenticate with SendPulse API.', $response);
        }

        $result = $response->toArray(false);
        $this->accessToken = (string) ($result['access_token'] ?? '');
        $this->tokenExpiry = time() + ((int) ($result['expires_in'] ?? 3600)) - 60;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

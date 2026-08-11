<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Pepipost\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Pepipost API transport.
 *
 * @since 0.1.0
 */
final class PepipostApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'emailapi.netcorecloud.net';
    /**
     * @var string
     */
    private const EU_HOST = 'apieu.netcorecloud.net';

    public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('pepipost+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v6/mail/send', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => 'x-api-key ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote Pepipost server.', $response, 0, $transportException);
        }

        if (! in_array($statusCode, [200, 202], true)) {
            try {
                $result = $response->toArray(false);
                $message = 'Unknown error';

                if (isset($result['message'])) {
                    $message = (string) $result['message'];
                } elseif (isset($result['error_info']['error_message'])) {
                    $message = (string) $result['error_info']['error_message'];
                }

                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($message) . sprintf(' (code %d).', $statusCode), $response);
            } catch (DecodingExceptionInterface $exception) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($response->getContent(false)) . sprintf(' (code %d).', $statusCode), $response, 0, $exception);
            }
        }

        try {
            $result = $response->toArray(false);

            if (is_string($result['data']['message_id'] ?? null) && $result['data']['message_id'] !== '') {
                $sentMessage->setMessageId($result['data']['message_id']);
            }
        } catch (DecodingExceptionInterface) {
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $address = $envelope->getSender();
        $payload = [
            'personalizations' => [[
                'recipient' => implode(',', array_map(static fn (Address $recipient): string => $recipient->getAddress(), $this->getRecipients($email, $envelope))),
            ]],
            'from' => ['fromEmail' => $address->getAddress()],
            'subject' => $email->getSubject(),
            'content' => [],
        ];

        if ($address->getName() !== '' && $address->getName() !== '0') {
            $payload['from']['fromName'] = $address->getName();
        }

        if ($email->getCc() !== []) {
            $payload['personalizations'][0]['recipient_cc'] = implode(',', array_map(static fn (Address $recipient): string => $recipient->getAddress(), $email->getCc()));
        }

        if ($email->getBcc() !== []) {
            $payload['personalizations'][0]['recipient_bcc'] = implode(',', array_map(static fn (Address $recipient): string => $recipient->getAddress(), $email->getBcc()));
        }

        if ($email->getReplyTo() !== []) {
            $replyTo = reset($email->getReplyTo());

            if ($replyTo instanceof Address) {
                $payload['replyToId'] = $replyTo->getAddress();
            }
        }

        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['content'][] = ['type' => 'text/plain', 'value' => $email->getTextBody()];
        }

        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['content'][] = ['type' => 'text/html', 'value' => $email->getHtmlBody()];
        }

        if ($email->getAttachments() !== []) {
            $payload['attachments'] = $this->getAttachments($email);
        }

        $customHeaders = [];
        $hasTags = false;
        $substitutions = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array(strtolower((string) $name), ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type'], true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $hasTags = true;
            } elseif ($header instanceof MetadataHeader) {
                $substitutions[$header->getKey()] = $header->getValue();
            } else {
                $customHeaders[$header->getName()] = $header->getBodyAsString();
            }
        }

        if ($customHeaders !== []) {
            $payload['headers'] = $customHeaders;
        }

        if ($hasTags) {
            $payload['settings'] = ['clicktrack' => 1, 'opentrack' => 1];
        }

        if ($substitutions !== []) {
            $payload['personalizations'][0]['attributes'] = $substitutions;
        }

        return $payload;
    }

    /** @return list<array<string, string>> */
    private function getAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $dataPart) {
            $headers = $dataPart->getPreparedHeaders();
            $attachments[] = [
                'fileContent' => base64_encode($dataPart->getBody()),
                'fileName' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'),
            ];
        }

        return $attachments;
    }

    private function getEndpoint(): string
    {
        if ($this->host !== null && $this->host !== 'default') {
            return $this->host . ($this->port === null ? '' : ':' . $this->port);
        }

        if ($this->region === 'eu') {
            return self::EU_HOST . ($this->port === null ? '' : ':' . $this->port);
        }

        return self::HOST . ($this->port === null ? '' : ':' . $this->port);
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\SparkPost\Transport;

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
 * SparkPost API transport.
 *
 * @since 0.1.0
 */
final class SparkPostApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'api.sparkpost.com';

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
        return sprintf('sparkpost+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/v1/transmissions', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote SparkPost server.', $response, 0, $transportException);
        }

        if ($statusCode !== 200) {
            try {
                $result = $response->toArray(false);
                $message = 'Unknown error';

                if (isset($result['errors']) && is_array($result['errors'])) {
                    $errors = array_map(static fn (array $error): string => (string) ($error['message'] ?? $error['description'] ?? 'Unknown error'), $result['errors']);
                    $message = implode('; ', $errors);
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

            if (is_string($result['results']['id'] ?? null) && $result['results']['id'] !== '') {
                $sentMessage->setMessageId($result['results']['id']);
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
            'recipients' => $this->formatRecipients($this->getRecipients($email, $envelope)),
            'content' => [
                'from' => ['email' => $address->getAddress()],
                'subject' => $email->getSubject(),
            ],
        ];

        if ($address->getName() !== '' && $address->getName() !== '0') {
            $payload['content']['from']['name'] = $address->getName();
        }

        if ($email->getCc() !== []) {
            foreach ($email->getCc() as $ccAddress) {
                $payload['recipients'][] = [
                    'address' => [
                        'email' => $ccAddress->getAddress(),
                        'name' => $ccAddress->getName(),
                        'header_to' => implode(',', array_map(static fn (Address $recipient): string => $recipient->getAddress(), $this->getRecipients($email, $envelope))),
                    ],
                ];
            }
        }

        if ($email->getBcc() !== []) {
            foreach ($email->getBcc() as $bccAddress) {
                $payload['recipients'][] = [
                    'address' => [
                        'email' => $bccAddress->getAddress(),
                        'name' => $bccAddress->getName(),
                        'header_to' => implode(',', array_map(static fn (Address $recipient): string => $recipient->getAddress(), $this->getRecipients($email, $envelope))),
                    ],
                ];
            }
        }

        if ($email->getReplyTo() !== []) {
            $replyTo = reset($email->getReplyTo());

            if ($replyTo instanceof Address) {
                $payload['content']['reply_to'] = $replyTo->getAddress();
            }
        }

        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['content']['text'] = $email->getTextBody();
        }

        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['content']['html'] = $email->getHtmlBody();
        }

        if ($email->getAttachments() !== []) {
            $payload['content']['attachments'] = $this->getAttachments($email);
        }

        $customHeaders = [];
        $tags = [];
        $metadata = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array(strtolower((string) $name), ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type'], true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
            } elseif ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
            } else {
                $customHeaders[$header->getName()] = $header->getBodyAsString();
            }
        }

        if ($customHeaders !== []) {
            $payload['content']['headers'] = $customHeaders;
        }

        if ($tags !== []) {
            $payload['options'] = ['open_tracking' => true, 'click_tracking' => true];
        }

        if ($metadata !== []) {
            foreach ($payload['recipients'] as &$recipient) {
                $recipient['metadata'] = $metadata;
            }
        }

        return $payload;
    }

    /** @param list<Address> $addresses @return list<array<string, array<string, string>>> */
    private function formatRecipients(array $addresses): array
    {
        $recipients = [];

        foreach ($addresses as $address) {
            $recipient = ['address' => ['email' => $address->getAddress()]];

            if ($address->getName() !== '') {
                $recipient['address']['name'] = $address->getName();
            }

            $recipients[] = $recipient;
        }

        return $recipients;
    }

    /** @return list<array<string, string>> */
    private function getAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $dataPart) {
            $headers = $dataPart->getPreparedHeaders();
            $attachments[] = [
                'name' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'),
                'type' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream',
                'data' => base64_encode($dataPart->getBody()),
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
            return 'api.eu.sparkpost.com' . ($this->port === null ? '' : ':' . $this->port);
        }

        return self::HOST . ($this->port === null ? '' : ':' . $this->port);
    }
}

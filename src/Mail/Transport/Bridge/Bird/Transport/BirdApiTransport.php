<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Bird\Transport;

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
 * Bird API transport.
 *
 * @link https://docs.bird.com/api/email-api/transmissions
 *
 * @since 0.1.0
 */
final class BirdApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const US_HOST = 'email.us-west-1.api.bird.com';
    /**
     * @var string
     */
    private const EU_HOST = 'email.eu-west-1.api.bird.com';

    /**
     * @since 0.1.0
     */
    public function __construct(
        #[SensitiveParameter] private readonly string $accessKey,
        private readonly string $workspaceId,
        private readonly ?string $region = null,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('bird+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/workspaces/' . $this->workspaceId . '/reach/transmissions', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => 'AccessKey ' . $this->accessKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote Bird server.', $response, 0, $transportException);
        }

        if (! in_array($statusCode, [200, 201], true)) {
            try {
                $result = $response->toArray(false);
                $message = 'Unknown error';

                if (isset($result['errors']) && is_array($result['errors'])) {
                    $errors = array_map(static fn (array $error): string => (string) ($error['message'] ?? $error['description'] ?? 'Unknown error'), $result['errors']);
                    $message = implode('; ', $errors);
                } elseif (isset($result['message'])) {
                    $message = (string) $result['message'];
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
            } elseif (is_string($result['id'] ?? null) && $result['id'] !== '') {
                $sentMessage->setMessageId($result['id']);
            }
        } catch (DecodingExceptionInterface) {
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $address = $envelope->getSender();
        $payload = [
            'recipients' => $this->formatRecipients($this->getRecipients($email, $envelope)),
            'content' => [
                'from' => [
                    'email' => $address->getAddress(),
                ],
                'subject' => $email->getSubject(),
            ],
        ];

        if ($address->getName() !== '' && $address->getName() !== '0') {
            $payload['content']['from']['name'] = $address->getName();
        }

        if ($email->getCc() !== []) {
            foreach ($email->getCc() as $ccAddress) {
                $recipient = ['address' => ['email' => $ccAddress->getAddress()]];

                if ($ccAddress->getName() !== '') {
                    $recipient['address']['name'] = $ccAddress->getName();
                }

                $recipient['address']['header_to'] = implode(',', array_map(static fn (Address $recipientAddress): string => $recipientAddress->getAddress(), $this->getRecipients($email, $envelope)));
                $payload['recipients'][] = $recipient;
            }
        }

        if ($email->getBcc() !== []) {
            foreach ($email->getBcc() as $bccAddress) {
                $recipient = ['address' => ['email' => $bccAddress->getAddress()]];

                if ($bccAddress->getName() !== '') {
                    $recipient['address']['name'] = $bccAddress->getName();
                }

                $recipient['address']['header_to'] = implode(',', array_map(static fn (Address $recipientAddress): string => $recipientAddress->getAddress(), $this->getRecipients($email, $envelope)));
                $payload['recipients'][] = $recipient;
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
        $metadata = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array(strtolower((string) $name), ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type'], true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $payload['campaign_id'] = $header->getValue();
            } elseif ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
            } else {
                $customHeaders[$header->getName()] = $header->getBodyAsString();
            }
        }

        if ($customHeaders !== []) {
            $payload['content']['headers'] = $customHeaders;
        }

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        return $payload;
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<array<string, array<string, string>>>
     */
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

    /**
     * @return list<array<string, string>>
     */
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

        $host = $this->region === 'us' ? self::US_HOST : self::EU_HOST;

        return $host . ($this->port === null ? '' : ':' . $this->port);
    }
}

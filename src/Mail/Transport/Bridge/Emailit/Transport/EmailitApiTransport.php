<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Emailit\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Emailit API transport.
 *
 * @since 0.1.0
 */
final class EmailitApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'api.emailit.com';

    public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('emailit+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v1/emails', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote Emailit server.', $response, 0, $transportException);
        }

        if (! in_array($statusCode, [200, 201, 202], true)) {
            try {
                $result = $response->toArray(false);
                $message = 'Unknown error';

                if (isset($result['message'])) {
                    $message = (string) $result['message'];
                } elseif (isset($result['error'])) {
                    $message = is_string($result['error']) ? $result['error'] : 'API error';
                } elseif (isset($result['errors']) && is_array($result['errors'])) {
                    $message = implode('; ', array_map('strval', $result['errors']));
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

            if (is_string($result['id'] ?? null) && $result['id'] !== '') {
                $sentMessage->setMessageId($result['id']);
            } elseif (is_string($result['data']['id'] ?? null) && $result['data']['id'] !== '') {
                $sentMessage->setMessageId($result['data']['id']);
            }
        } catch (DecodingExceptionInterface) {
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $address = $envelope->getSender();

        return [
            'from' => $address->getName() !== '' && $address->getName() !== '0' ? sprintf('%s <%s>', $address->getName(), $address->getAddress()) : $address->getAddress(),
            'to' => $this->getRecipients($email, $envelope)[0]->getAddress(),
            'subject' => $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody() ?: null,
            'reply_to' => $email->getReplyTo() !== [] ? reset($email->getReplyTo())?->getAddress() : null,
            'attachments' => $email->getAttachments() !== [] ? $this->getAttachments($email) : [],
            'headers' => json_encode($this->getHeaders($email)),
        ];
    }

    /** @return array<string, string> */
    private function getHeaders(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if ($header instanceof TagHeader || $header instanceof MetadataHeader) {
                continue;
            }

            $headers[(string) $name] = $header->getBodyAsString();
        }

        return $headers;
    }

    /** @return list<array<string, string>> */
    private function getAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $dataPart) {
            $headers = $dataPart->getPreparedHeaders();
            $attachments[] = [
                'content' => base64_encode($dataPart->getBody()),
                'filename' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'),
                'type' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream',
            ];
        }

        return $attachments;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

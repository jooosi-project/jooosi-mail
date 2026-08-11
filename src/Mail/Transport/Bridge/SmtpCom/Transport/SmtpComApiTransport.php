<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\SmtpCom\Transport;

use JsonException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * SMTP.com API transport.
 *
 * @since 0.1.0
 */
final class SmtpComApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'api.smtp.com';

    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $channel = null,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('smtpcom+api://api.smtp.com%s', $this->channel === null || $this->channel === '' ? '' : '?channel=' . $this->channel);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . self::HOST . '/v4/messages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $this->buildPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);

            if ($statusCode !== 200) {
                $message = $result['data']['errors'] ?? $result['message'] ?? 'Unknown error';

                if (is_array($message)) {
                    $message = implode(', ', array_values($message));
                }

                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException(sprintf('Unable to send email: %s (code %d).', esc_html($message), $statusCode), $response);
            }

            $sentMessage->setMessageId((string) ($result['data']['msg_id'] ?? ''));

            return $response;
        } catch (JsonException $jsonException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException(sprintf('Unable to send email: invalid JSON response (%s).', $jsonException->getMessage()), $response, $jsonException->getCode(), $jsonException);
        }
    }

    /** @return array<string, mixed> */
    private function buildPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'subject' => $email->getSubject(),
            'to' => $this->formatRecipients($envelope->getRecipients()),
            'from' => $envelope->getSender()->getAddress(),
        ];

        if (($fromHeader = $email->getFrom()[0] ?? null) !== null && $fromHeader->getName() !== '') {
            $payload['from'] = sprintf('%s <%s>', $fromHeader->getName(), $fromHeader->getAddress());
        }

        if (($replyToHeader = $email->getReplyTo()[0] ?? null) !== null) {
            $payload['reply_to'] = $replyToHeader->getAddress();
        }

        if ($this->channel !== null && $this->channel !== '') {
            $payload['channel'] = $this->channel;
        }

        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['html_body'] = $email->getHtmlBody();
        }

        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['text_body'] = $email->getTextBody();
        }

        $attachments = [];

        foreach ($email->getAttachments() as $dataPart) {
            if ($dataPart instanceof DataPart) {
                $attachments[] = [
                    'name' => $dataPart->getFilename() ?: 'attachment',
                    'data' => base64_encode($dataPart->getBody()),
                    'type' => $dataPart->getContentType(),
                ];
            }
        }

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        $customHeaders = [];

        foreach ($email->getHeaders()->all() as $header) {
            $name = $header->getName();

            if (in_array(strtolower($name), ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to'], true)) {
                continue;
            }

            $customHeaders[$name] = $header->getBodyAsString();
        }

        if ($customHeaders !== []) {
            $payload['custom_headers'] = $customHeaders;
        }

        return $payload;
    }

    /** @param list<object> $recipients @return list<string> */
    private function formatRecipients(array $recipients): array
    {
        return array_map(static fn ($recipient): string => $recipient->getAddress(), $recipients);
    }
}

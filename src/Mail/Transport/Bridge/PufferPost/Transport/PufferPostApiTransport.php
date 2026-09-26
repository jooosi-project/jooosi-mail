<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\PufferPost\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * PufferPost REST API transport.
 *
 * @since 0.1.0
 */
final class PufferPostApiTransport extends AbstractApiTransport
{
    private const HOST = 'pufferpost.com';

    private const METADATA_HEADER = 'x-pufferpost-metadata';

    private const UNSUBSCRIBE_GROUP_HEADER = 'x-pufferpost-unsubscribe-group';

    private const LOCALE_HEADER = 'x-pufferpost-locale';

    private const TIMEZONE_HEADER = 'x-pufferpost-timezone';

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
        return sprintf('pufferpost+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/v1/messages/batch', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => ['messages' => $this->getPayload($email, $envelope)],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send an email via PufferPost: ' . esc_html($response->getContent(false)) . sprintf(' (code %d).', $response->getStatusCode()), $response, 0, $exception);
        } catch (TransportExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the PufferPost API.', $response, 0, $exception);
        }

        if (! in_array($statusCode, [200, 202], true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send an email via PufferPost: ' . esc_html($this->formatErrorMessage($result)) . sprintf(' (code %d).', $statusCode), $response);
        }

        $items = $result['data'] ?? [];

        foreach ($items as $item) {
            if (($item['status'] ?? null) !== 'accepted') {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email via PufferPost: ' . esc_html((string) ($item['error']['message'] ?? 'the message was rejected')) . sprintf(' (code %d).', $statusCode), $response);
            }
        }

        if (is_string($items[0]['id'] ?? null) && $items[0]['id'] !== '') {
            $sentMessage->setMessageId($items[0]['id']);
        }

        return $response;
    }

    /** @return list<array<string, mixed>> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $from = $email->getFrom();
        $shared = [
            'from' => $from !== [] ? $from[0]->toString() : $envelope->getSender()->toString(),
        ];

        if ($email->getSubject() !== null) {
            $shared['subject'] = $email->getSubject();
        }

        if ($email->getTextBody() !== null) {
            $shared['text'] = $email->getTextBody();
        }

        if ($email->getHtmlBody() !== null) {
            $shared['html'] = $email->getHtmlBody();
        }

        if (($metadata = $this->jsonOption($email, self::METADATA_HEADER)) !== null) {
            $shared['metadata'] = $metadata;
        }

        foreach ([
            self::UNSUBSCRIBE_GROUP_HEADER => 'unsubscribeGroup',
            self::LOCALE_HEADER => 'locale',
            self::TIMEZONE_HEADER => 'timezone',
        ] as $headerName => $fieldName) {
            if (($value = $this->option($email, $headerName)) !== null) {
                $shared[$fieldName] = $value;
            }
        }

        $replyTo = $email->getReplyTo();

        if ($replyTo !== []) {
            $shared['replyTo'] = $replyTo[0]->toString();
        }

        if (($attachments = $this->getAttachments($email)) !== []) {
            $shared['attachments'] = $attachments;
        }

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (! is_string($name) || ! str_starts_with($name, 'x-') || str_starts_with($name, 'x-pufferpost-')) {
                continue;
            }

            $shared['headers'][$header->getName()] = $header->getBodyAsString();
        }

        $recipients = $this->getRecipients($email, $envelope);
        $cc = $this->filterEnvelopeAddresses($email->getCc(), $envelope);
        $bcc = $this->filterEnvelopeAddresses($email->getBcc(), $envelope);

        if ($recipients === []) {
            $recipients = $envelope->getRecipients();
            $cc = [];
            $bcc = [];
        }

        $messages = [];

        foreach ($recipients as $index => $recipient) {
            $message = ['to' => $recipient->toString()] + $shared;

            if ($index === 0) {
                if ($cc !== []) {
                    $message['cc'] = $cc;
                }

                if ($bcc !== []) {
                    $message['bcc'] = $bcc;
                }
            }

            $messages[] = $message;
        }

        return $messages;
    }

    private function option(Email $email, string $name): ?string
    {
        $value = $email->getHeaders()->getHeaderBody($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<array-key, mixed>|null */
    private function jsonOption(Email $email, string $name): ?array
    {
        $rawValue = $this->option($email, $name);

        if ($rawValue === null) {
            return null;
        }

        $decoded = json_decode($rawValue, true);

        if (! is_array($decoded)) {
            throw new TransportException(sprintf('The "%s" header must contain a JSON object.', $name));
        }

        return $decoded;
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<string>
     */
    private function filterEnvelopeAddresses(array $addresses, Envelope $envelope): array
    {
        $allowedAddresses = array_map(static fn (Address $address): string => $address->getAddress(), $envelope->getRecipients());
        $filteredAddresses = [];

        foreach ($addresses as $address) {
            if (in_array($address->getAddress(), $allowedAddresses, true)) {
                $filteredAddresses[] = $address->toString();
            }
        }

        return $filteredAddresses;
    }

    /** @return list<array{filename: string, contentType: string, content: string}> */
    private function getAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = strtolower((string) ($headers->getHeaderBody('Content-Disposition') ?? 'attachment'));

            if (str_starts_with($disposition, 'inline') || $headers->has('Content-ID')) {
                throw new TransportException('PufferPost does not support inline (cid-embedded) attachments; attach the file or host the image at a URL instead.');
            }

            $attachments[] = [
                'filename' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'),
                'contentType' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream',
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        return $attachments;
    }

    /** @param array<string, mixed> $result */
    private function formatErrorMessage(array $result): string
    {
        if (is_string($result['error']['message'] ?? null) && $result['error']['message'] !== '') {
            return $result['error']['message'];
        }

        return 'Unknown error';
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

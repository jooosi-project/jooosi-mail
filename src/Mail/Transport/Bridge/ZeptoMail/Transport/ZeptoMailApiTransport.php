<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\ZeptoMail\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\HttpTransportException;
use JooosiMailDeps\Symfony\Component\Mailer\Header\MetadataHeader;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractApiTransport;
use JooosiMailDeps\Symfony\Component\Mime\Address;
use JooosiMailDeps\Symfony\Component\Mime\Email;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * ZeptoMail API transport.
 *
 * @link https://www.zoho.com/zeptomail/help/api/email-sending.html
 *
 * @since 0.1.0
 */
final class ZeptoMailApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.zeptomail.com';
    /**
     * @since 0.1.0
     */
    public function __construct(
        #[SensitiveParameter]
        private readonly string $apiToken,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }
    /**
     * @since 0.1.0
     */
    public function __toString(): string
    {
        return sprintf('zeptomail+api://%s', $this->getEndpoint());
    }
    /**
     * @since 0.1.0
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v1.1/email', ['json' => $this->getPayload($email, $envelope), 'headers' => ['Accept' => 'application/json', 'Authorization' => 'zoho-enczapikey ' . $this->apiToken]]);
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the remote ZeptoMail server.', $response, 0, $transportException);
        }
        if (!in_array($statusCode, [200, 202], \true)) {
            try {
                $result = $response->toArray(\false);
                $message = is_string($result['message'] ?? null) ? $result['message'] : 'Unknown error';
                $details = $result['details'] ?? null;
                if (is_array($details) && $details !== []) {
                    $message .= ': ' . implode('; ', array_map('strval', $details));
                }
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($message) . sprintf(' (code %d).', $statusCode), $response);
            } catch (DecodingExceptionInterface $exception) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send an email: ' . esc_html($response->getContent(\false)) . sprintf(' (code %d).', $statusCode), $response, 0, $exception);
            }
        }
        try {
            $result = $response->toArray(\false);
            if (is_string($result['request_id'] ?? null) && $result['request_id'] !== '') {
                $sentMessage->setMessageId($result['request_id']);
            }
        } catch (DecodingExceptionInterface) {
        }
        return $response;
    }
    /**
     * @since 0.1.0
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $from = $envelope->getSender();
        $payload = ['from' => ['address' => $from->getAddress()], 'to' => $this->formatAddresses($this->getRecipients($email, $envelope)), 'subject' => $email->getSubject()];
        if ($from->getName() !== '' && $from->getName() !== '0') {
            $payload['from']['name'] = $from->getName();
        }
        if ($email->getCc() !== []) {
            $payload['cc'] = $this->formatAddresses($email->getCc());
        }
        if ($email->getBcc() !== []) {
            $payload['bcc'] = $this->formatAddresses($email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $payload['reply_to'] = $this->formatReplyToAddresses($email->getReplyTo());
        }
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['textbody'] = $email->getTextBody();
        }
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['htmlbody'] = $email->getHtmlBody();
        }
        if ($email->getAttachments() !== []) {
            $payload['attachments'] = $this->getAttachments($email);
        }
        $mimeHeaders = [];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array(strtolower((string) $name), ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type'], \true)) {
                continue;
            }
            if ($header instanceof MetadataHeader && $header->getKey() === 'client_reference') {
                $payload['client_reference'] = (string) $header->getValue();
                continue;
            }
            $mimeHeaders[$header->getName()] = $header->getBodyAsString();
        }
        if ($mimeHeaders !== []) {
            $payload['mime_headers'] = $mimeHeaders;
        }
        return $payload;
    }
    /**
     * @param list<Address> $addresses
     *
     * @return list<array<string, array<string, string>>>
     *
     * @since 0.1.0
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(function (Address $address): array {
            $formatted = ['email_address' => ['address' => $address->getAddress()]];
            if ($address->getName() !== '' && $address->getName() !== '0') {
                $formatted['email_address']['name'] = $address->getName();
            }
            return $formatted;
        }, $addresses);
    }
    /**
     * @param list<Address> $addresses
     *
     * @return list<array<string, string>>
     *
     * @since 0.1.0
     */
    private function formatReplyToAddresses(array $addresses): array
    {
        return array_map(function (Address $address): array {
            $formatted = ['address' => $address->getAddress()];
            if ($address->getName() !== '' && $address->getName() !== '0') {
                $formatted['name'] = $address->getName();
            }
            return $formatted;
        }, $addresses);
    }
    /**
     * @return list<array<string, string>>
     *
     * @since 0.1.0
     */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $dataPart) {
            $headers = $dataPart->getPreparedHeaders();
            $contentType = $headers->get('Content-Type');
            $attachments[] = ['content' => base64_encode($dataPart->getBody()), 'mime_type' => $contentType === null ? 'application/octet-stream' : $contentType->getBody(), 'name' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment')];
        }
        return $attachments;
    }
    /**
     * @since 0.1.0
     */
    private function getEndpoint(): string
    {
        $host = $this->host ?? self::HOST;
        return $host . ($this->port === null ? '' : ':' . $this->port);
    }
}

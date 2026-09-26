<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\TurboSmtp\Transport;

use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use SensitiveParameter;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\HttpTransportException;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractApiTransport;
use JooosiMailDeps\Symfony\Component\Mime\Address;
use JooosiMailDeps\Symfony\Component\Mime\Email;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * TurboSMTP REST API transport.
 *
 * @since 0.1.0
 */
final class TurboSmtpApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.turbo-smtp.com';
    /** @var list<string> */
    private const PAYLOAD_HEADERS = ['bcc', 'cc', 'content-transfer-encoding', 'content-type', 'date', 'dkim-signature', 'from', 'message-id', 'mime-version', 'received', 'reply-to', 'return-path', 'subject', 'to'];
    public function __construct(
        #[SensitiveParameter]
        private readonly string $consumerKey,
        #[SensitiveParameter]
        private readonly string $consumerSecret,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null
    )
    {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }
    public function __toString(): string
    {
        return sprintf('turbosmtp+api://%s', $this->getEndpoint());
    }
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/v2/mail/send', ['headers' => ['consumerKey' => $this->consumerKey, 'consumerSecret' => $this->consumerSecret], 'json' => $this->getPayload($email, $envelope)]);
        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(\false);
        } catch (DecodingExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send an email via TurboSMTP: ' . esc_html($response->getContent(\false)) . sprintf(' (code %d).', $response->getStatusCode()), $response, 0, $exception);
        } catch (TransportExceptionInterface $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the TurboSMTP API.', $response, 0, $exception);
        }
        if ($statusCode !== 200 || strtolower((string) ($result['message'] ?? '')) === 'error') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to send an email via TurboSMTP: ' . esc_html($this->formatErrorMessage($result)) . sprintf(' (code %d).', $statusCode), $response);
        }
        if (is_string($result['mid'] ?? null) && $result['mid'] !== '') {
            $sentMessage->setMessageId($result['mid']);
        }
        return $response;
    }
    /** @return array<string, mixed> */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = ['from' => $envelope->getSender()->toString(), 'to' => implode(',', array_map(static fn(Address $address): string => $address->toString(), $this->getRecipients($email, $envelope))), 'subject' => (string) $email->getSubject()];
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['content'] = $email->getTextBody();
        }
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['html_content'] = $email->getHtmlBody();
        }
        if ($email->getCc() !== []) {
            $payload['cc'] = implode(',', array_map(static fn(Address $address): string => $address->toString(), $email->getCc()));
        }
        if ($email->getBcc() !== []) {
            $payload['bcc'] = implode(',', array_map(static fn(Address $address): string => $address->toString(), $email->getBcc()));
        }
        if ($email->getReplyTo() !== []) {
            $payload['custom_headers']['Reply-To'] = implode(',', array_map(static fn(Address $address): string => $address->toString(), $email->getReplyTo()));
        }
        if ($email->getAttachments() !== []) {
            $attachments = $this->getAttachments($email);
            $payload['attachments'] = $attachments;
            if (isset($payload['html_content'])) {
                $payload['html_content'] = $this->qualifyInlineCids($payload['html_content'], $attachments, $envelope->getSender()->getAddress());
            }
        }
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array(strtolower((string) $name), self::PAYLOAD_HEADERS, \true)) {
                continue;
            }
            $payload['custom_headers'][$header->getName()] = $header->getBodyAsString();
        }
        return $payload;
    }
    /** @return list<array{name: string, type: string, content: string, content_id?: string}> */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment');
            $item = ['name' => $filename, 'type' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream', 'content' => base64_encode($attachment->getBody())];
            if (strtolower((string) ($headers->getHeaderBody('Content-Disposition') ?? '')) === 'inline') {
                $item['content_id'] = $attachment->hasContentId() ? $attachment->getContentId() : $filename;
            }
            $attachments[] = $item;
        }
        return $attachments;
    }
    /**
     * @param list<array{name: string, type: string, content: string, content_id?: string}> $attachments
     */
    private function qualifyInlineCids(string $html, array $attachments, string $sender): string
    {
        $senderAtPosition = strrchr($sender, '@');
        $domain = $senderAtPosition === \false ? '' : substr($senderAtPosition, 1);
        if ($domain === '') {
            return $html;
        }
        foreach ($attachments as $attachment) {
            $contentId = $attachment['content_id'] ?? null;
            if ($contentId === null || str_contains($contentId, '@')) {
                continue;
            }
            $html = preg_replace('/cid:' . preg_quote($contentId, '/') . '(?![\w.@-])/', 'cid:' . $contentId . '@' . $domain, $html) ?? $html;
        }
        return $html;
    }
    /** @param array<string, mixed> $result */
    private function formatErrorMessage(array $result): string
    {
        $errors = $result['errors'] ?? null;
        if (is_array($errors)) {
            $messages = array_filter(array_map(static fn(mixed $error): string => is_scalar($error) ? trim((string) $error) : '', $errors));
            if ($messages !== []) {
                return implode(', ', $messages);
            }
        } elseif (is_string($errors) && trim($errors) !== '') {
            return trim($errors);
        }
        if (is_string($result['message'] ?? null) && $result['message'] !== '') {
            return $result['message'];
        }
        return 'Unknown error';
    }
    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
    }
}

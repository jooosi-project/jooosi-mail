<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Transport\Bridge\Gmail\Transport;

use JooosiMail\Mail\Transport\Bridge\Gmail\GmailTokenManager;
use JooosiMailDeps\Psr\EventDispatcher\EventDispatcherInterface;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mailer\Exception\HttpTransportException;
use JooosiMailDeps\Symfony\Component\Mailer\SentMessage;
use JooosiMailDeps\Symfony\Component\Mailer\Transport\AbstractApiTransport;
use JooosiMailDeps\Symfony\Component\Mime\Email;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\HttpClientInterface;
use JooosiMailDeps\Symfony\Contracts\HttpClient\ResponseInterface;
/**
 * Gmail API transport backed by service-account OAuth.
 *
 * @since 0.1.0
 */
final class GmailApiTransport extends AbstractApiTransport
{
    /**
     * @var string
     */
    private const HOST = 'gmail.googleapis.com';
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly GmailTokenManager $tokenManager, private readonly string $userEmail, ?HttpClientInterface $httpClient = null, ?EventDispatcherInterface $eventDispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($httpClient, $eventDispatcher, $logger);
    }
    /**
     * @since 0.1.0
     */
    public function __toString(): string
    {
        return sprintf('gmail+api://%s', $this->userEmail);
    }
    /**
     * @since 0.1.0
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', $this->getEndpoint(), ['json' => ['raw' => $this->base64UrlEncode($sentMessage->toString())], 'auth_bearer' => $this->tokenManager->getToken()]);
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the Gmail API server.', $response, 0, $transportException);
        }
        if ($statusCode !== 200) {
            try {
                $result = $response->toArray(\false);
                $message = (string) ($result['error']['message'] ?? $result['error_description'] ?? $result['message'] ?? 'Unknown error');
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send email via Gmail API: ' . esc_html($message) . sprintf(' (code %d).', $statusCode), $response);
            } catch (DecodingExceptionInterface $exception) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new HttpTransportException('Unable to send email via Gmail API: ' . esc_html($response->getContent(\false)) . sprintf(' (code %d).', $statusCode), $response, 0, $exception);
            }
        }
        try {
            $result = $response->toArray(\false);
            if (is_string($result['id'] ?? null) && $result['id'] !== '') {
                $sentMessage->setMessageId($result['id']);
            }
        } catch (DecodingExceptionInterface) {
        }
        return $response;
    }
    /**
     * @since 0.1.0
     */
    private function getEndpoint(): string
    {
        $host = ($this->host ?: self::HOST) . ($this->port === null ? '' : ':' . $this->port);
        return 'https://' . $host . '/gmail/v1/users/' . rawurlencode($this->userEmail) . '/messages/send';
    }
    /**
     * @since 0.1.0
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

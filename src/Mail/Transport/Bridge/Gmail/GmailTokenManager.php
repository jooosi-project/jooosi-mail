<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Transport\Bridge\Gmail;

use SensitiveParameter;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Manages OAuth2 access tokens for Gmail service accounts.
 *
 * @since 0.1.0
 */
final class GmailTokenManager
{
    /**
     * @var string
     */
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    /**
     * @var string
     */
    private const GMAIL_SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    private ?string $token = null;

    private ?int $tokenExpiresAt = null;

    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly string $serviceAccountEmail,
        #[SensitiveParameter] private readonly string $privateKey,
        private readonly string $userEmail,
        private readonly HttpClientInterface $client,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function getToken(): string
    {
        if ($this->token !== null && $this->tokenExpiresAt !== null && time() < $this->tokenExpiresAt) {
            return $this->token;
        }

        $response = $this->client->request('POST', self::TOKEN_ENDPOINT, [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->createJwt(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Could not reach the Google OAuth2 server.', $response, 0, $transportException);
        }

        if ($statusCode !== 200) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to authenticate with Google: ' . esc_html($response->getContent(false)) . sprintf(' (code %d).', $statusCode), $response);
        }

        $tokenData = $response->toArray(false);
        $accessToken = is_string($tokenData['access_token'] ?? null) ? $tokenData['access_token'] : null;
        $expiresIn = max(0, (int) ($tokenData['expires_in'] ?? 3600));

        if ($accessToken === null || $accessToken === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpTransportException('Unable to authenticate with Google: missing access token in response.', $response);
        }

        $this->token = $accessToken;
        $this->tokenExpiresAt = time() + max(0, $expiresIn - 60);

        return $this->token;
    }

    /**
     * @since 0.1.0
     */
    private function createJwt(): string
    {
        $now = time();
        $header = $this->encodeJson([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]);
        $claims = $this->encodeJson([
            'iss' => $this->serviceAccountEmail,
            'sub' => $this->userEmail,
            'scope' => self::GMAIL_SEND_SCOPE,
            'aud' => self::TOKEN_ENDPOINT,
            'iat' => $now,
            'exp' => $now + 3600,
        ]);
        $signatureInput = $this->base64UrlEncode($header) . '.' . $this->base64UrlEncode($claims);

        return $signatureInput . '.' . $this->base64UrlEncode($this->sign($signatureInput));
    }

    /**
     * @param array<string, int|string> $payload
     *
     * @since 0.1.0
     */
    private function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            throw new InvalidArgumentException('Failed to encode Google service account JWT payload.');
        }

        return $json;
    }

    /**
     * @since 0.1.0
     */
    private function sign(string $data): string
    {
        if (! function_exists('openssl_pkey_get_private') || ! function_exists('openssl_sign')) {
            throw new InvalidArgumentException('The OpenSSL extension is required for Gmail service account authentication.');
        }

        $privateKey = openssl_pkey_get_private($this->privateKey);

        if ($privateKey === false) {
            throw new InvalidArgumentException('Invalid private key provided for Gmail service account authentication.');
        }

        if (! openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new InvalidArgumentException('Failed to sign JWT for Gmail service account authentication.');
        }

        return $signature;
    }

    /**
     * @since 0.1.0
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

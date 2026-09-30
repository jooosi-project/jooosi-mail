<?php

declare(strict_types=1);

namespace JooosiMail\Alert\Channel;

use JooosiMail\Alert\AlertDeliveryException;
use JooosiMail\Discovery\Attribute\Service;

/**
 * Sends JSON requests to alert providers and normalizes provider errors.
 *
 * @since 1.0.12
 */
#[Service]
final class AlertHttpClient
{
    /**
     * Post a JSON payload to a provider endpoint.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     *
     * @since 1.0.12
     */
    public function postJson(
        string $provider,
        string $url,
        array $body,
        array $headers,
        string $credential,
        bool $blocking,
        bool $requiresOkResponse,
    ): void {
        $encodedBody = wp_json_encode($body);

        if (! is_string($encodedBody)) {
            throw new AlertDeliveryException('The alert message could not be encoded.');
        }

        $response = wp_safe_remote_post($url, [
            'timeout' => $blocking ? 10 : 0.01,
            'blocking' => $blocking,
            'headers' => array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers),
            'body' => $encodedBody,
            'data_format' => 'body',
            'sslverify' => true,
            'limit_response_size' => 8192,
        ]);

        if (is_wp_error($response)) {
            throw new AlertDeliveryException('The notification request could not be completed. Check outbound HTTPS access and channel credentials.');
        }

        if (! is_array($response)) {
            throw new AlertDeliveryException('The notification provider returned an invalid response.');
        }

        if (! $blocking) {
            return;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status < 200 || $status >= 300) {
            $providerError = $this->providerErrorMessage($response, $credential);
            $details = $providerError !== '' ? ' ' . $providerError : '';

            throw new AlertDeliveryException(sprintf(
                '%s rejected the alert (HTTP %d).%s',
                $provider,
                $status,
                $details,
            ));
        }

        if (! $requiresOkResponse) {
            return;
        }

        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($responseBody) || ($responseBody['ok'] ?? false) !== true) {
            $providerError = $this->providerErrorMessage($response, $credential);
            $details = $providerError !== '' ? ': ' . $providerError : ' (invalid provider response)';

            throw new AlertDeliveryException(sprintf('%s did not accept the alert%s.', $provider, $details));
        }
    }

    /**
     * Extract a short provider error without returning a configured credential.
     *
     * @param array<string, mixed> $response
     *
     * @since 1.0.12
     */
    private function providerErrorMessage(array $response, string $credential): string
    {
        $payload = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($payload)) {
            return '';
        }

        $message = $payload['description'] ?? $payload['message'] ?? $payload['error'] ?? '';

        if (! is_scalar($message)) {
            return '';
        }

        $message = sanitize_text_field((string) $message);

        if ($credential !== '') {
            $message = str_replace($credential, '[credential redacted]', $message);
        }

        return wp_html_excerpt($message, 240, '…');
    }
}

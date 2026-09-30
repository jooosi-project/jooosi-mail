<?php

declare(strict_types=1);

namespace JooosiMail\Alert\Channel;

use InvalidArgumentException;
use JooosiMail\Alert\AlertMessage;
use JooosiMail\Discovery\Attribute\Service;

/**
 * Discord channel webhook implementation for mail failure alerts.
 *
 * @since 1.0.12
 */
#[Service]
final class DiscordAlertChannel implements AlertChannelDriverInterface
{
    /**
     * @since 1.0.12
     */
    public function __construct(
        private readonly AlertHttpClient $httpClient,
    ) {
    }

    /**
     * @since 1.0.12
     */
    public function key(): string
    {
        return 'discord';
    }

    /**
     * @since 1.0.12
     */
    public function displayName(): string
    {
        return 'Discord';
    }

    /**
     * @since 1.0.12
     */
    public function secretOptionField(): string
    {
        return 'webhook_url';
    }

    /**
     * @since 1.0.12
     */
    public function targetOptionField(): ?string
    {
        return null;
    }

    /**
     * @since 1.0.12
     */
    public function validateSecret(string $secret): void
    {
        if (strlen($secret) > 1024) {
            throw new InvalidArgumentException('The Discord webhook URL is too long.');
        }

        $parts = wp_parse_url($secret);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), ['discord.com', 'discordapp.com'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || preg_match('/^\/api(?:\/v\d+)?\/webhooks\/\d+\/[A-Za-z0-9._-]+\/?$/D', (string) ($parts['path'] ?? '')) !== 1
        ) {
            throw new InvalidArgumentException('Enter a valid HTTPS Discord channel webhook URL.');
        }
    }

    /**
     * @since 1.0.12
     */
    public function validateTarget(string $target): void
    {
    }

    /**
     * @param array{enabled: bool, configured: bool, hasSavedCredential: bool, secret: string, target: string} $credentials
     *
     * @since 1.0.12
     */
    public function deliver(array $credentials, AlertMessage $message, bool $blocking): void
    {
        $this->httpClient->postJson(
            $this->displayName(),
            $credentials['secret'],
            ['content' => $message->toPlainText(), 'allowed_mentions' => ['parse' => []]],
            [],
            $credentials['secret'],
            $blocking,
            false,
        );
    }
}

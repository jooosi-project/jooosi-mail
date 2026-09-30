<?php

declare(strict_types=1);

namespace JooosiMail\Alert\Channel;

use InvalidArgumentException;
use JooosiMail\Alert\AlertMessage;
use JooosiMail\Discovery\Attribute\Service;

/**
 * Telegram Bot API implementation for mail failure alerts.
 *
 * @since 1.0.12
 */
#[Service]
final class TelegramAlertChannel implements AlertChannelDriverInterface
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
        return 'telegram';
    }

    /**
     * @since 1.0.12
     */
    public function displayName(): string
    {
        return 'Telegram';
    }

    /**
     * @since 1.0.12
     */
    public function secretOptionField(): string
    {
        return 'bot_token';
    }

    /**
     * @since 1.0.12
     */
    public function targetOptionField(): ?string
    {
        return 'chat_id';
    }

    /**
     * @since 1.0.12
     */
    public function validateSecret(string $secret): void
    {
        if (strlen($secret) > 1024 || preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/D', $secret) !== 1) {
            throw new InvalidArgumentException('Enter a valid Telegram bot token from BotFather.');
        }
    }

    /**
     * @since 1.0.12
     */
    public function validateTarget(string $target): void
    {
        if (preg_match('/^(?:-?\d+|@[A-Za-z0-9_]{5,32})$/D', $target) !== 1) {
            throw new InvalidArgumentException('Enter a Telegram chat ID or public group/channel username.');
        }
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
            sprintf('https://api.telegram.org/bot%s/sendMessage', $credentials['secret']),
            ['chat_id' => $credentials['target'], 'text' => $message->toPlainText()],
            [],
            $credentials['secret'],
            $blocking,
            true,
        );
    }
}

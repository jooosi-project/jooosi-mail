<?php

declare(strict_types=1);

namespace JooosiMail\Alert\Channel;

use InvalidArgumentException;
use JooosiMail\Alert\AlertMessage;
use JooosiMail\Discovery\Attribute\Service;

/**
 * Slack chat.postMessage implementation for mail failure alerts.
 *
 * @since 1.0.12
 */
#[Service]
final class SlackAlertChannel implements AlertChannelDriverInterface
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
        return 'slack';
    }

    /**
     * @since 1.0.12
     */
    public function displayName(): string
    {
        return 'Slack';
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
        return 'channel_id';
    }

    /**
     * @since 1.0.12
     */
    public function validateSecret(string $secret): void
    {
        if (strlen($secret) > 1024 || preg_match('/^xoxb-[A-Za-z0-9-]+$/D', $secret) !== 1) {
            throw new InvalidArgumentException('Enter a Slack bot user OAuth token beginning with xoxb-.');
        }
    }

    /**
     * @since 1.0.12
     */
    public function validateTarget(string $target): void
    {
        if (preg_match('/^[CGD][A-Z0-9]+$/D', $target) !== 1) {
            throw new InvalidArgumentException('Enter a Slack channel ID, such as C0123456789.');
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
            'https://slack.com/api/chat.postMessage',
            ['channel' => $credentials['target'], 'text' => $this->formatMessage($message)],
            ['Authorization' => 'Bearer ' . $credentials['secret']],
            $credentials['secret'],
            $blocking,
            true,
        );
    }

    /**
     * Format the shared alert fields using Slack mrkdwn.
     *
     * @since 1.0.12
     */
    private function formatMessage(AlertMessage $message): string
    {
        $icon = $message->isTest ? ':white_check_mark:' : ':warning:';
        $lines = [$icon . ' *' . $this->escapeMrkdwn($message->title) . '*'];
        $lines[] = '*Site:* ' . $this->escapeMrkdwn($message->siteName);

        if ($message->subject !== null) {
            $lines[] = '*Subject:* ' . $this->escapeMrkdwn($message->subject);
        }

        if ($message->error !== null) {
            $lines[] = '*Error:* ' . $this->escapeMrkdwn($message->error);
        }

        if ($message->logUrl !== null && $message->mailLogId !== null) {
            $lines[] = sprintf('<%s|View mail log #%d>', $message->logUrl, $message->mailLogId);
        }

        return implode("\n", $lines);
    }

    /**
     * Escape dynamic values embedded in Slack mrkdwn.
     *
     * @since 1.0.12
     */
    private function escapeMrkdwn(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Alert;

use InvalidArgumentException;
use JooosiMail\Alert\Channel\AlertChannelRegistry;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Logging\MailLogRepository;
use Throwable;

/**
 * Delivers mail failure alerts to configured external channels.
 *
 * @since 1.0.12
 */
#[Service]
final class AlertNotifier
{
    /**
     * @since 1.0.12
     */
    public function __construct(
        private readonly AlertConfigurationService $configurationService,
        private readonly AlertChannelRegistry $channelRegistry,
        private readonly MailLogRepository $mailLogRepository,
        private readonly EventPublisherInterface $eventPublisher,
    ) {
    }

    /**
     * Notify enabled channels after a message reaches terminal failure.
     *
     * @since 1.0.12
     */
    public function notifyFailure(int $mailLogId, string $error): void
    {
        if ($mailLogId <= 0) {
            return;
        }

        $log = $this->mailLogRepository->find($mailLogId) ?? [];
        $message = $this->buildFailureMessage($mailLogId, $log, $error);

        foreach ($this->channelRegistry->all() as $channel) {
            try {
                $credentials = $this->configurationService->credentials($channel->key());

                if (! $credentials['enabled'] || ! $credentials['configured']) {
                    continue;
                }

                $channel->deliver($credentials, $message, false);
            } catch (Throwable $throwable) {
                try {
                    $this->eventPublisher->doAction('a!jooosi-mail/alerts:send.failed', $channel->key(), $mailLogId, $throwable);
                } catch (Throwable) {
                }
            }
        }
    }

    /**
     * Send a synchronous test alert so the settings screen can report success.
     *
     * @since 1.0.12
     */
    public function sendTest(string $channelKey): void
    {
        $channel = $this->channelRegistry->get($channelKey);
        $credentials = $this->configurationService->credentials($channelKey);

        if (! $credentials['configured']) {
            throw new InvalidArgumentException(sprintf('Configure %s before sending a test alert.', $channel->displayName()));
        }

        $siteName = $this->plainText(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), 120);
        $siteName = $siteName !== '' ? $siteName : home_url();
        $message = new AlertMessage('Jooosi Mail test alert', $siteName, isTest: true);

        $channel->deliver($credentials, $message, true);
    }

    /**
     * @param array<string, mixed> $log
     *
     * @since 1.0.12
     */
    private function buildFailureMessage(int $mailLogId, array $log, string $error): AlertMessage
    {
        $siteName = $this->plainText(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), 120);
        $siteName = $siteName !== '' ? $siteName : home_url();
        $subject = $this->plainText((string) ($log['subject'] ?? ''), 240);
        $error = $this->plainText($error, 700);
        $logUrl = admin_url(sprintf('admin.php?page=jooosi-mail#/logs/mail?id=%d', $mailLogId));

        return new AlertMessage(
            'Jooosi Mail: email delivery failed',
            $siteName,
            $subject !== '' ? $subject : '(no subject)',
            $error !== '' ? $error : 'Unknown delivery error.',
            $mailLogId,
            $logUrl,
        );
    }

    /**
     * @since 1.0.12
     */
    private function plainText(string $value, int $maxLength): string
    {
        $value = trim((string) (preg_replace('/\s+/u', ' ', wp_strip_all_tags($value)) ?? $value));

        return $value !== '' ? wp_html_excerpt($value, $maxLength, '…') : '';
    }
}

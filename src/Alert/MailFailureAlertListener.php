<?php

declare(strict_types=1);

namespace JooosiMail\Alert;

use JooosiMail\Discovery\Attribute\Hook;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use Throwable;

/**
 * Starts configured alerts when a mail reaches terminal failure.
 *
 * @since 1.0.12
 */
#[Service]
final class MailFailureAlertListener
{
    /**
     * @since 1.0.12
     */
    public function __construct(
        private readonly AlertNotifier $alertNotifier,
        private readonly EventPublisherInterface $eventPublisher,
    ) {
    }

    /**
     * @since 1.0.12
     */
    #[Hook(name: 'a!jooosi-mail/mail:failed', kind: 'action', acceptedArgs: 2)]
    public function onMailFailed(int $mailLogId, string $error): void
    {
        try {
            $this->alertNotifier->notifyFailure($mailLogId, $error);
        } catch (Throwable $throwable) {
            try {
                $this->eventPublisher->doAction('a!jooosi-mail/alerts:notify.failed', $mailLogId, $throwable);
            } catch (Throwable) {
            }
        }
    }
}

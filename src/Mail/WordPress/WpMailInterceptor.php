<?php

declare (strict_types=1);
namespace JooosiMail\Mail\WordPress;

use JooosiMail\Discovery\Attribute\Hook;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Submission\MailSubmissionService;
use Throwable;
/**
 * Intercepts `wp_mail()` and hands off to Jooosi Mail.
 *
 * @since 0.1.0
 */
#[Service]
final class WpMailInterceptor
{
    private bool $intercepting = \false;
    public function __construct(private readonly \JooosiMail\Mail\WordPress\WpMailPayloadNormalizer $payloadNormalizer, private readonly MailSubmissionService $mailSubmissionService, private readonly OptionStore $optionStore, private readonly EventPublisherInterface $eventPublisher)
    {
    }
    /**
     * Replace native sending with Jooosi Mail.
     *
     * @param array<string, mixed> $args
     *
     * @since 0.1.0
     */
    #[Hook(name: 'pre_wp_mail', kind: 'filter', priority: 9999, acceptedArgs: 2)]
    public function intercept(?bool $preempt, array $args): ?bool
    {
        if ($preempt !== null || $this->intercepting || !$this->isEnabled()) {
            return $preempt;
        }
        $this->intercepting = \true;
        try {
            $mailRequest = $this->payloadNormalizer->normalize($args);
            $submissionResult = $this->mailSubmissionService->submitWithResult($mailRequest);
            if ($mailRequest->source === 'admin_test_email') {
                $this->eventPublisher->doAction('a!jooosi-mail/mail:test.submitted', $submissionResult);
            }
            return $submissionResult->accepted;
        } catch (Throwable $throwable) {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:intercept.failed', $throwable, $args);
            return \false;
        } finally {
            $this->intercepting = \false;
        }
    }
    /**
     * @since 0.1.0
     */
    private function isEnabled(): bool
    {
        $enabled = (bool) $this->optionStore->get('settings.mail.intercept.enabled', \true);
        return (bool) $this->eventPublisher->applyFilters('f!jooosi-mail/mail:intercept.enabled', $enabled);
    }
}

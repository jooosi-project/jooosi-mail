<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Mail;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Submission\MailSubmissionService;
use JooosiMail\Mail\ValueObject\MailRequest;
use JooosiMail\Mail\ValueObject\MailSubmissionResult;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
/**
 * Submits emails composed in the WordPress admin.
 *
 * @since 1.0.12
 */
#[Service]
final class ComposedEmailSender
{
    /**
     * @since 1.0.12
     */
    public function __construct(private readonly MailSubmissionService $mailSubmissionService, private readonly EventPublisherInterface $eventPublisher, private readonly LoggerInterface $logger)
    {
    }
    /**
     * Applies the compose request filter before submission and emits a submitted action afterward.
     *
     * @param MailRequest $mailRequest Normalized message payload.
     * @param int $requestedByUserId WordPress user ID of the admin who submitted the email.
     * @return MailSubmissionResult Submission status and mail log ID.
     * @since 1.0.12
     */
    public function send(MailRequest $mailRequest, int $requestedByUserId): MailSubmissionResult
    {
        $mailRequest = $mailRequest->with(['source' => 'admin_compose', 'metadata' => array_merge($mailRequest->metadata, ['admin_compose_requested_by_user_id' => $requestedByUserId > 0 ? $requestedByUserId : null])]);
        try {
            $filteredMailRequest = $this->eventPublisher->applyFilters('f!jooosi-mail/mail:compose.request', $mailRequest, $requestedByUserId);
            $result = $this->mailSubmissionService->submitWithResult($filteredMailRequest instanceof MailRequest ? $filteredMailRequest : $mailRequest);
        } catch (Throwable $throwable) {
            $this->logger->error('Admin composed email submission failed.', ['requested_by_user_id' => $requestedByUserId, 'exception' => $throwable]);
            throw new RuntimeException('The composed email could not be submitted.', previous: $throwable);
        }
        try {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:compose.submitted', $result->mailLogId, $requestedByUserId, $result->accepted);
        } catch (Throwable $throwable) {
            $this->logger->error('Admin composed email notification failed.', ['mail_log_id' => $result->mailLogId, 'requested_by_user_id' => $requestedByUserId, 'exception' => $throwable]);
        }
        return $result;
    }
}

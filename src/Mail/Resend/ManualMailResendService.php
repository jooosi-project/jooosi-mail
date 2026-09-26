<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Resend;

use JsonException;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\Resend\Exception\InvalidMailLogPayloadException;
use JooosiMail\Mail\Resend\Exception\MailLogNotFoundException;
use JooosiMail\Mail\Resend\Exception\MailResendException;
use JooosiMail\Mail\Submission\MailSubmissionService;
use JooosiMail\Mail\ValueObject\MailRequest;
use JooosiMail\Mail\ValueObject\MailSubmissionResult;
use JooosiMailDeps\Psr\Log\LoggerInterface;
use Throwable;
/**
 * Reconstructs retained mail and submits it as a fresh delivery.
 *
 * @since 1.0.9
 */
#[Service]
final class ManualMailResendService
{
    /**
     * Headers that must be regenerated for a fresh delivery.
     *
     * @since 1.0.9
     */
    private const FRESH_DELIVERY_HEADERS = ['date', 'message-id', 'x-schedule-time'];
    /**
     * @since 1.0.9
     */
    public function __construct(private readonly MailLogRepository $mailLogRepository, private readonly MailSubmissionService $mailSubmissionService, private readonly EventPublisherInterface $eventPublisher, private readonly LoggerInterface $logger)
    {
    }
    /**
     * Submits a new delivery without changing the source mail log.
     *
     * @since 1.0.9
     */
    public function resend(int $mailLogId, int $requestedByUserId = 0): MailSubmissionResult
    {
        $mailLog = $this->mailLogRepository->find($mailLogId);
        if (!is_array($mailLog)) {
            throw new MailLogNotFoundException(sprintf('Mail log %d was not found.', $mailLogId));
        }
        $mailRequest = $this->createMailRequest($mailLogId, $mailLog['payload_json'] ?? null, $requestedByUserId);
        try {
            $filteredMailRequest = $this->eventPublisher->applyFilters('f!jooosi-mail/mail:resend.request', $mailRequest, $mailLogId);
            $result = $this->mailSubmissionService->submitWithResult($filteredMailRequest instanceof MailRequest ? $filteredMailRequest : $mailRequest);
        } catch (Throwable $throwable) {
            $this->logger->error('Manual email resubmission failed.', ['source_mail_log_id' => $mailLogId, 'exception' => $throwable]);
            throw new MailResendException(sprintf('Mail log %d could not be submitted again.', $mailLogId), previous: $throwable);
        }
        try {
            $this->eventPublisher->doAction('a!jooosi-mail/mail:resend.submitted', $mailLogId, $result->mailLogId, $result->accepted);
        } catch (Throwable $throwable) {
            $this->logger->error('Manual resend notification failed.', ['source_mail_log_id' => $mailLogId, 'new_mail_log_id' => $result->mailLogId, 'exception' => $throwable]);
        }
        return $result;
    }
    /**
     * @since 1.0.9
     */
    private function createMailRequest(int $mailLogId, mixed $payloadJson, int $requestedByUserId): MailRequest
    {
        if (!is_string($payloadJson) || $payloadJson === '') {
            throw new InvalidMailLogPayloadException(sprintf('Mail log %d does not contain a reusable email payload.', $mailLogId));
        }
        try {
            $payload = json_decode($payloadJson, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidMailLogPayloadException(sprintf('Mail log %d does not contain a valid email payload.', $mailLogId), previous: $exception);
        }
        if (!is_array($payload)) {
            throw new InvalidMailLogPayloadException(sprintf('Mail log %d does not contain a valid email payload.', $mailLogId));
        }
        try {
            $sourceRequest = MailRequest::fromArray($payload);
        } catch (Throwable $throwable) {
            throw new InvalidMailLogPayloadException(sprintf('Mail log %d could not be reconstructed for resending.', $mailLogId), previous: $throwable);
        }
        if (array_merge($sourceRequest->to, $sourceRequest->cc, $sourceRequest->bcc) === []) {
            throw new InvalidMailLogPayloadException(sprintf('Mail log %d does not contain any recipients.', $mailLogId));
        }
        return $sourceRequest->with(['headers' => $this->prepareHeaders($sourceRequest->headers), 'source' => 'manual_resend', 'metadata' => array_merge($sourceRequest->metadata, ['manual_resend_of_mail_log_id' => $mailLogId, 'manual_resend_requested_by_user_id' => $requestedByUserId > 0 ? $requestedByUserId : null, 'manual_resend_requested_at' => gmdate('c')])]);
    }
    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     *
     * @since 1.0.9
     */
    private function prepareHeaders(array $headers): array
    {
        return array_filter($headers, static fn(string $name): bool => !in_array(strtolower($name), self::FRESH_DELIVERY_HEADERS, \true), \ARRAY_FILTER_USE_KEY);
    }
}

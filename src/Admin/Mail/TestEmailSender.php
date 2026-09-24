<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Mail;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\ValueObject\MailRequest;
use JooosiMail\Mail\ValueObject\MailSubmissionResult;
use Throwable;
use WP_Error;

/**
 * Sends generated Jooosi Mail diagnostic test messages.
 *
 * @since 0.1.0
 */
#[Service]
final class TestEmailSender
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly TestEmailTemplateRenderer $templateRenderer,
        private readonly MailAttemptRepository $mailAttemptRepository,
        private readonly MailLogRepository $mailLogRepository,
    ) {
    }

    /**
     * Send a generated diagnostic test email through `wp_mail()`.
     *
     * @return TestEmailResult Delivery result with an error message when available.
     * @since 0.1.0
     */
    public function send(string $to, ?string $subject = null, ?int $connectionId = null): TestEmailResult
    {
        $subject = sanitize_text_field((string) ($subject ?? ''));

        if ($subject === '') {
            $subject = __('Jooosi Mail test', 'jooosi-mail');
        }

        $bodies = $this->templateRenderer->render(
            recipientSummary: $to,
            connectionLabel: __('Not routed by Jooosi Mail', 'jooosi-mail'),
            connectionProfile: __('WordPress native wp_mail()', 'jooosi-mail'),
            deliveryMode: __('Native wp_mail', 'jooosi-mail'),
            routingStrategy: __('None', 'jooosi-mail'),
            mailLogLabel: __('Not logged by Jooosi Mail', 'jooosi-mail'),
            mailLogUrl: admin_url('admin.php?page=jooosi-mail#/logs/mail'),
        );
        $testRequestMarked = false;
        $markTestMailRequest = function (MailRequest $mailRequest) use (&$testRequestMarked, $bodies): MailRequest {
            if ($testRequestMarked) {
                return $mailRequest;
            }

            $testRequestMarked = true;

            return new MailRequest(
                from: $mailRequest->from,
                to: $mailRequest->to,
                cc: $mailRequest->cc,
                bcc: $mailRequest->bcc,
                replyTo: $mailRequest->replyTo,
                subject: $mailRequest->subject,
                textBody: $mailRequest->textBody,
                htmlBody: $mailRequest->htmlBody,
                attachments: $mailRequest->attachments,
                headers: $mailRequest->headers,
                envelopeSender: $mailRequest->envelopeSender,
                source: 'admin_test_email',
                metadata: array_merge($mailRequest->metadata, [TestEmailDeliveryTemplateListener::METADATA_KEY => true]),
            );
        };
        $setAltBody = static function (object $phpmailer) use ($bodies): void {
            if (property_exists($phpmailer, 'AltBody')) {
                $phpmailer->AltBody = $bodies['textBody'];
            }
        };
        /** @var MailSubmissionResult|null $submissionResult */
        $submissionResult = null;
        $capturedError = null;
        $captureSubmissionResult = static function (MailSubmissionResult $result) use (&$submissionResult): void {
            $submissionResult = $result;
        };
        $captureInterceptionFailure = function (Throwable $throwable, array $args) use ($to, $subject, &$capturedError): void {
            $matchesTestMail = $this->matchesWpMailArguments(
                $args['to'] ?? null,
                $args['subject'] ?? null,
                $to,
                $subject,
            );

            if ($matchesTestMail || $capturedError === null) {
                $capturedError = $throwable->getMessage();
            }
        };
        $captureWordPressFailure = function (WP_Error $error) use ($to, $subject, &$capturedError): void {
            $data = $error->get_error_data();

            if (is_array($data) && $this->matchesWpMailArguments($data['to'] ?? null, $data['subject'] ?? null, $to, $subject)) {
                $capturedError = $error->get_error_message();

                return;
            }

            if ($capturedError === null) {
                $capturedError = $error->get_error_message();
            }
        };

        add_filter('f!jooosi-mail/mail:normalize.request', $markTestMailRequest, 10, 1);
        add_action('phpmailer_init', $setAltBody);
        add_action('a!jooosi-mail/mail:test.submitted', $captureSubmissionResult, 10, 1);
        add_action('a!jooosi-mail/mail:intercept.failed', $captureInterceptionFailure, 10, 2);
        add_action('wp_mail_failed', $captureWordPressFailure, 10, 1);

        try {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $connectionId = max(0, (int) ($connectionId ?? 0));

            if ($connectionId > 0) {
                $headers[] = sprintf('X-Jooosi-Mail-Connection-Id: %d', $connectionId);
            }

            $sent = wp_mail($to, $subject, $bodies['htmlBody'], $headers);

            if ($sent) {
                return new TestEmailResult(sent: true);
            }

            return new TestEmailResult(
                sent: false,
                errorMessage: $this->resolveFailureMessage($submissionResult, $capturedError)
                    ?? __('wp_mail() returned false without reporting an error.', 'jooosi-mail'),
            );
        } catch (Throwable $throwable) {
            return new TestEmailResult(
                sent: false,
                errorMessage: $this->normalizeErrorMessage($throwable->getMessage()) ?? __(
                    'wp_mail() threw an exception without reporting an error message.',
                    'jooosi-mail',
                ),
            );
        } finally {
            remove_filter('f!jooosi-mail/mail:normalize.request', $markTestMailRequest, 10);
            remove_action('phpmailer_init', $setAltBody);
            remove_action('a!jooosi-mail/mail:test.submitted', $captureSubmissionResult, 10);
            remove_action('a!jooosi-mail/mail:intercept.failed', $captureInterceptionFailure, 10);
            remove_action('wp_mail_failed', $captureWordPressFailure, 10);
        }
    }

    /**
     * @since 1.0.9
     */
    private function matchesWpMailArguments(mixed $to, mixed $subject, string $recipient, string $expectedSubject): bool
    {
        if (! is_scalar($subject) || (string) $subject !== $expectedSubject) {
            return false;
        }

        $recipients = is_array($to) ? $to : (is_string($to) ? explode(',', $to) : []);

        foreach ($recipients as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            if (preg_match('/<([^>]+)>/', $candidate, $matches) === 1) {
                $candidate = $matches[1];
            }

            if (strcasecmp(sanitize_email(trim($candidate)), $recipient) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @since 1.0.9
     */
    private function resolveFailureMessage(?MailSubmissionResult $submissionResult, ?string $capturedError): ?string
    {
        $capturedMessage = $this->normalizeErrorMessage((string) ($capturedError ?? ''));

        if ($capturedMessage !== null) {
            return $capturedMessage;
        }

        $submissionMessage = $this->normalizeErrorMessage((string) ($submissionResult?->error ?? ''));

        if ($submissionMessage !== null) {
            return $submissionMessage;
        }

        if ($submissionResult === null) {
            return null;
        }

        $attemptErrors = [];

        try {
            foreach ($this->mailAttemptRepository->listRecent(
                limit: 20,
                mailLogId: $submissionResult->mailLogId,
                status: 'failed',
            ) as $attempt) {
                $error = $this->normalizeErrorMessage((string) ($attempt['error_message'] ?? ''));

                if ($error !== null) {
                    $connectionName = $this->normalizeErrorMessage((string) ($attempt['connection_name'] ?? ''));
                    $error = $connectionName !== null ? sprintf('%s: %s', $connectionName, $error) : $error;
                    $attemptErrors[$error] = $error;
                }

                if (count($attemptErrors) >= 3) {
                    break;
                }
            }

            if ($attemptErrors !== []) {
                return implode(' | ', array_values($attemptErrors));
            }

            $mailLog = $this->mailLogRepository->find($submissionResult->mailLogId);
        } catch (Throwable) {
            return null;
        }

        return is_array($mailLog)
            ? $this->normalizeErrorMessage((string) ($mailLog['last_error'] ?? ''))
            : null;
    }

    /**
     * @since 1.0.9
     */
    private function normalizeErrorMessage(string $message): ?string
    {
        $message = sanitize_text_field($message);

        return $message !== '' ? $message : null;
    }

}

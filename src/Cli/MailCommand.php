<?php

declare (strict_types=1);
namespace JooosiMail\Cli;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JooosiMail\Admin\Mail\TestEmailSender;
use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Submission\MailSubmissionService;
use JooosiMail\Mail\ValueObject\MailAddress;
use JooosiMail\Mail\ValueObject\MailRequest;
use Throwable;
use WP_CLI;
use function WP_CLI\Utils\format_items;
/**
 * Run mail diagnostics.
 *
 * ## EXAMPLES
 *
 *     # Send a smoke test email.
 *     $ wp jooosi-mail mail:test --to=you@example.com
 *     Success: The test email was queued or sent successfully.
 *
 *     # Override the subject.
 *     $ wp jooosi-mail mail:test --to=you@example.com --subject="Queue check"
 *     Success: The test email was queued or sent successfully.
 *
 *     # Send through a preferred connection.
 *     $ wp jooosi-mail mail:test --to=you@example.com --connection-id=3
 *     Success: The test email was queued or sent successfully.
 *
 *     # Send a plain text email.
 *     $ wp jooosi-mail mail:send --to=you@example.com --subject="Hello" --text-message="Hello from WP-CLI."
 *     Success: Email #42 was queued or sent successfully.
 *
 * @since 0.1.0
 */
#[Service]
final class MailCommand
{
    public function __construct(private readonly TestEmailSender $testEmailSender, private readonly MailAttemptRepository $mailAttemptRepository, private readonly MailSubmissionService $mailSubmissionService)
    {
    }
    /**
     * Send an email through Jooosi Mail.
     *
     * ## OPTIONS
     *
     * --to=<to>
     * : Comma-separated recipient email addresses.
     *
     * --subject=<subject>
     * : Email subject line.
     *
     * [--text-message=<text-message>]
     * : Plain text body. Provide this, --html-message, or both.
     *
     * [--html-message=<html-message>]
     * : HTML body. Provide this, --text-message, or both.
     *
     * [--cc=<cc>]
     * : Comma-separated CC email addresses.
     *
     * [--bcc=<bcc>]
     * : Comma-separated BCC email addresses.
     *
     * [--reply-to=<reply-to>]
     * : Comma-separated reply-to email addresses.
     *
     * [--send-at=<datetime>]
     * : Schedule delivery using an ISO 8601 date/time, Unix timestamp in seconds, or strtotime-compatible expression. Local values use the WordPress timezone.
     *
     * [--connection-id=<connection-id>]
     * : Preferred Jooosi Mail connection id to use.
     *
     * ## EXAMPLES
     *
     *     # Send a plain text email.
     *     $ wp jooosi-mail mail:send --to=you@example.com --subject="Hello" --text-message="Hello from WP-CLI."
     *     Success: Email #42 was queued or sent successfully.
     *
     *     # Send HTML to multiple recipients through a preferred connection.
     *     $ wp jooosi-mail mail:send --to="you@example.com,team@example.com" --subject="Update" --html-message="<p>All set.</p>" --connection-id=3
     *     Success: Email #43 was queued or sent successfully.
     *
     *     # Send both plain text and HTML alternatives.
     *     $ wp jooosi-mail mail:send --to=you@example.com --subject="Update" --text-message="All set." --html-message="<p>All set.</p>"
     *     Success: Email #44 was queued or sent successfully.
     *
     *     # Schedule delivery for 9:00 AM Jakarta time.
     *     $ wp jooosi-mail mail:send --to=you@example.com --subject="Reminder" --text-message="Your reminder." --send-at="2030-01-01T09:00:00+07:00"
     *     Success: Email #45 was queued or sent successfully.
     *
     *     # Schedule delivery using a relative date or Unix timestamp.
     *     $ wp jooosi-mail mail:send --to=you@example.com --subject="Reminder" --text-message="Your reminder." --send-at="tomorrow 9am"
     *     $ wp jooosi-mail mail:send --to=you@example.com --subject="Reminder" --text-message="Your reminder." --send-at=1893488400
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 1.0.12
     */
    #[Command(description: 'Send an email through Jooosi Mail.')]
    public function send(array $args, array $assocArgs): void
    {
        $to = $this->normalizeAddresses($assocArgs['to'] ?? null, 'to');
        if ($to === []) {
            WP_CLI::error('Provide at least one valid recipient with --to=<email>.');
        }
        if (!isset($assocArgs['subject']) || !is_scalar($assocArgs['subject'])) {
            WP_CLI::error('Provide --subject=<subject>.');
        }
        $hasTextBody = array_key_exists('text-message', $assocArgs);
        $hasHtmlBody = array_key_exists('html-message', $assocArgs);
        if (!$hasTextBody && !$hasHtmlBody) {
            WP_CLI::error('Provide --text-message=<text-message>, --html-message=<html-message>, or both.');
        }
        if ($hasTextBody && !is_scalar($assocArgs['text-message'])) {
            WP_CLI::error('Provide a scalar value for --text-message=<text-message>.');
        }
        if ($hasHtmlBody && !is_scalar($assocArgs['html-message'])) {
            WP_CLI::error('Provide a scalar value for --html-message=<html-message>.');
        }
        $subject = sanitize_text_field((string) $assocArgs['subject']);
        $connectionId = isset($assocArgs['connection-id']) ? max(0, (int) $assocArgs['connection-id']) : 0;
        $headers = [];
        if (array_key_exists('send-at', $assocArgs)) {
            $sendAt = $this->parseScheduleDateTime($assocArgs['send-at']);
            if ($sendAt === null) {
                WP_CLI::error('Provide --send-at as an ISO 8601 date/time, Unix timestamp in seconds, or strtotime-compatible expression.');
            }
            if ($sendAt->getTimestamp() <= time()) {
                WP_CLI::error('--send-at must be a future date and time.');
            }
            $headers['X-Schedule-Time'] = $sendAt->format(DateTimeInterface::ATOM);
        }
        if ($connectionId > 0) {
            $headers['X-Jooosi-Mail-Connection-Id'] = (string) $connectionId;
        }
        $mailRequest = new MailRequest(from: [], to: $to, cc: $this->normalizeAddresses($assocArgs['cc'] ?? null, 'cc'), bcc: $this->normalizeAddresses($assocArgs['bcc'] ?? null, 'bcc'), replyTo: $this->normalizeAddresses($assocArgs['reply-to'] ?? null, 'reply-to'), subject: $subject, textBody: $hasTextBody ? (string) $assocArgs['text-message'] : null, htmlBody: $hasHtmlBody ? (string) $assocArgs['html-message'] : null, attachments: [], headers: $headers, source: 'wp_cli', metadata: $connectionId > 0 ? ['preferred_connection_id' => $connectionId] : []);
        try {
            $result = $this->mailSubmissionService->submitWithResult($mailRequest);
        } catch (Throwable $throwable) {
            $error = trim($throwable->getMessage());
            WP_CLI::error($error !== '' ? $error : 'The email could not be queued or sent.');
        }
        if (!$result->accepted) {
            WP_CLI::error($result->error ?? 'The email could not be queued or sent.');
        }
        WP_CLI::success(sprintf('Email #%d was queued or sent successfully.', $result->mailLogId));
    }
    /**
     * Send a test email through Jooosi Mail.
     *
     * ## OPTIONS
     *
     * --to=<to>
     * : Recipient email address.
     *
     * [--subject=<subject>]
     * : Subject line to send.
     *
     * [--connection-id=<connection-id>]
     * : Preferred Jooosi Mail connection id to use.
     *
     * ## EXAMPLES
     *
     *     # Send a smoke test email.
     *     $ wp jooosi-mail mail:test --to=you@example.com
     *     Success: The test email was queued or sent successfully.
     *
     *     # Send a custom-subject test email.
     *     $ wp jooosi-mail mail:test --to=you@example.com --subject="Queue check"
     *     Success: The test email was queued or sent successfully.
     *
     *     # Send through a preferred connection.
     *     $ wp jooosi-mail mail:test --to=you@example.com --connection-id=3
     *     Success: The test email was queued or sent successfully.
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Send a test email through Jooosi Mail.')]
    public function test(array $args, array $assocArgs): void
    {
        $to = sanitize_email((string) ($assocArgs['to'] ?? ''));
        if ($to === '') {
            WP_CLI::error('Provide --to=<email>.');
        }
        if (!is_email($to)) {
            WP_CLI::error('Enter a valid recipient email address.');
        }
        $result = $this->testEmailSender->send($to, (string) ($assocArgs['subject'] ?? 'Jooosi Mail test'), isset($assocArgs['connection-id']) ? max(0, (int) $assocArgs['connection-id']) : null);
        if ($result->sent) {
            WP_CLI::success('The test email was queued or sent successfully.');
            return;
        }
        WP_CLI::error($result->errorMessage ?? 'The test email failed.');
    }
    /**
     * List recent Jooosi Mail delivery attempts.
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Maximum number of attempts to show.
     * ---
     * default: 20
     * ---
     *
     * [--mail-log-id=<mail-log-id>]
     * : Filter attempts to a specific mail log id.
     *
     * [--connection-id=<connection-id>]
     * : Filter attempts to a specific connection id.
     *
     * [--status=<status>]
     * : Filter attempts by status.
     *
     * ## EXAMPLES
     *
     *     # List the most recent delivery attempts.
     *     $ wp jooosi-mail mail:attempts --limit=10
     *     id  mail_log_id  connection        status  started_at           finished_at          transport_message_id  error
     *     18  42           #3 Primary SMTP   sent    2026-03-23 09:15:00  2026-03-23 09:15:00  01HR...              
     *
     *     # Filter attempts for a single mail log.
     *     $ wp jooosi-mail mail:attempts --mail-log-id=42
     *     id  mail_log_id  connection        status  started_at           finished_at          transport_message_id  error
     *     18  42           #3 Primary SMTP   sent    2026-03-23 09:15:00  2026-03-23 09:15:00  01HR...              
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'List recent Jooosi Mail delivery attempts.')]
    public function attempts(array $args, array $assocArgs): void
    {
        $rows = $this->mailAttemptRepository->listRecent(limit: max(1, (int) ($assocArgs['limit'] ?? 20)), mailLogId: isset($assocArgs['mail-log-id']) ? max(1, (int) $assocArgs['mail-log-id']) : null, connectionId: isset($assocArgs['connection-id']) ? max(1, (int) $assocArgs['connection-id']) : null, status: $this->normalizeFilter($assocArgs['status'] ?? null));
        if ($rows === []) {
            WP_CLI::success('No delivery attempts found.');
            return;
        }
        $items = array_map(fn(array $row): array => ['id' => (string) (int) ($row['id'] ?? 0), 'mail_log_id' => (string) (int) ($row['mail_log_id'] ?? 0), 'connection' => $this->formatConnectionLabel($row), 'status' => (string) ($row['status'] ?? ''), 'started_at' => (string) ($row['started_at'] ?? ''), 'finished_at' => (string) ($row['finished_at'] ?? ''), 'transport_message_id' => $this->formatValue((string) ($row['transport_message_id'] ?? '')), 'error' => $this->formatError((string) ($row['error_message'] ?? ''))], $rows);
        format_items('table', $items, ['id', 'mail_log_id', 'connection', 'status', 'started_at', 'finished_at', 'transport_message_id', 'error']);
    }
    /**
     * @param array<string, mixed> $row
     *
     * @since 0.1.0
     */
    private function formatConnectionLabel(array $row): string
    {
        $connectionId = isset($row['connection_id']) ? (int) $row['connection_id'] : 0;
        $connectionName = trim((string) ($row['connection_name'] ?? ''));
        if ($connectionId > 0 && $connectionName !== '') {
            return sprintf('#%d %s', $connectionId, $connectionName);
        }
        if ($connectionId > 0) {
            return sprintf('#%d', $connectionId);
        }
        return 'n/a';
    }
    /**
     * @since 0.1.0
     */
    private function formatValue(string $value): string
    {
        $value = trim($value);
        return $value !== '' ? $value : '';
    }
    /**
     * @since 0.1.0
     */
    private function formatError(string $error): string
    {
        $normalized = trim((string) (preg_replace('/\s+/', ' ', $error) ?? $error));
        if ($normalized === '') {
            return '';
        }
        if (strlen($normalized) <= 80) {
            return $normalized;
        }
        return substr($normalized, 0, 77) . '...';
    }
    /**
     * @since 0.1.0
     */
    private function normalizeFilter(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $normalized = strtolower(trim((string) $value));
        return $normalized !== '' ? $normalized : null;
    }
    /**
     * @since 1.0.12
     */
    private function parseScheduleDateTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d+$/D', $value) === 1) {
            try {
                return (new DateTimeImmutable('@' . $value))->setTimezone(wp_timezone());
            } catch (Throwable) {
                return null;
            }
        }
        $siteTimezone = wp_timezone();
        $utcTimezone = new DateTimeZone('UTC');
        $formats = [[DateTimeInterface::ATOM, $siteTimezone], ['Y-m-d\TH:iP', $siteTimezone], ['Y-m-d\TH:i:s\Z', $utcTimezone], ['Y-m-d\TH:i\Z', $utcTimezone], ['Y-m-d H:i:s', $siteTimezone], ['Y-m-d H:i', $siteTimezone], ['Y-m-d\TH:i:s', $siteTimezone], ['Y-m-d\TH:i', $siteTimezone]];
        foreach ($formats as [$format, $timezone]) {
            $dateTime = DateTimeImmutable::createFromFormat('!' . $format, $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($dateTime !== \false && ($errors === \false || $errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $dateTime;
            }
        }
        try {
            $dateTime = new DateTimeImmutable($value, $siteTimezone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors !== \false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return null;
            }
            return $dateTime;
        } catch (Throwable) {
            return null;
        }
    }
    /**
     * @return list<MailAddress>
     *
     * @since 1.0.12
     */
    private function normalizeAddresses(mixed $value, string $option): array
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return [];
        }
        $addresses = [];
        $candidates = str_getcsv((string) $value, ',', '"', '\\');
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $name = null;
            $address = $candidate;
            if (preg_match('/^(.*?)\s*<([^<>]+)>$/', $candidate, $matches) === 1) {
                $name = trim($matches[1], "\" ");
                $address = trim($matches[2]);
                $name = $name !== '' ? sanitize_text_field($name) : null;
            }
            $address = sanitize_email($address);
            if (!is_email($address)) {
                WP_CLI::error(sprintf('Enter a valid email address for --%s.', $option));
            }
            $addresses[] = new MailAddress($address, $name);
        }
        return $addresses;
    }
}

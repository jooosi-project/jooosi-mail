<?php

declare (strict_types=1);
namespace JooosiMail\Cli;

use JooosiMail\Admin\Mail\TestEmailSender;
use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Logging\MailAttemptRepository;
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
 * @since 0.1.0
 */
#[Service]
final class MailCommand
{
    public function __construct(private readonly TestEmailSender $testEmailSender, private readonly MailAttemptRepository $mailAttemptRepository)
    {
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
        if ($result) {
            WP_CLI::success('The test email was queued or sent successfully.');
            return;
        }
        WP_CLI::error('The test email failed.');
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
}

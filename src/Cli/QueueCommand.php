<?php

declare(strict_types=1);

namespace JooosiMail\Cli;

use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Failure\FailedMessageRepository;
use JooosiMail\Queue\Failure\FailedMessageService;
use JooosiMail\Queue\Maintenance\QueueMaintenanceService;
use JooosiMail\Queue\Query\QueueMessageQuery;
use JooosiMail\Queue\Transport\DatabaseTransport;
use JooosiMail\Queue\Worker\QueueWorker;
use WP_CLI;

use function WP_CLI\Utils\format_items;

/**
 * Manage queue processing.
 *
 * ## EXAMPLES
 *
 *     # Inspect queue counts.
 *     $ wp jooosi-mail queue:status
 *     Ready Pending: 12
 *     Deferred Pending: 3
 *     Processing: 2
 *     Stale Claims: 1
 *     Failed: 2
 *
 *     # Process a small batch of queued messages.
 *     $ wp jooosi-mail queue:work --limit=10
 *     Success: Processed 10 queue message(s).
 *
 * @since 0.1.0
 */
#[Service]
final class QueueCommand
{
    public function __construct(
        private readonly QueueMessageQuery $queueMessageQuery,
        private readonly QueueMaintenanceService $queueMaintenanceService,
        private readonly FailedMessageRepository $failedMessageRepository,
        private readonly FailedMessageService $failedMessageService,
        private readonly QueueWorker $queueWorker,
    ) {
    }

    /**
     * Show Jooosi Mail queue status.
     *
     * ## EXAMPLES
     *
     *     # Inspect queue counts, including stale claims.
     *     $ wp jooosi-mail queue:status
     *     Ready Pending: 12
     *     Deferred Pending: 3
     *     Processing: 2
     *     Stale Claims: 1
     *     Failed: 2
     *
     *     # Use a shorter stale threshold while investigating worker recovery.
     *     $ wp jooosi-mail queue:status --stale-after=120
     *     Ready Pending: 4
     *     Deferred Pending: 0
     *     Processing: 1
     *     Stale Claims: 0
     *     Failed: 0
     *
     * ## OPTIONS
     *
     * [--stale-after=<stale-after>]
     * : Age in seconds before a processing claim is considered stale.
     * ---
     * default: 300
     * ---
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Show Jooosi Mail queue status.')]
    public function status(array $args, array $assocArgs): void
    {
        $staleAfter = max(1, (int) ($assocArgs['stale-after'] ?? 300));
        $snapshot = $this->queueMessageQuery->getStatusSnapshot($staleAfter);

        WP_CLI::line(sprintf('Ready Pending: %d', $snapshot['pending_ready']));
        WP_CLI::line(sprintf('Deferred Pending: %d', $snapshot['pending_deferred']));
        WP_CLI::line(sprintf('Processing: %d', $snapshot['processing']));
        WP_CLI::line(sprintf('Stale Claims: %d', $snapshot['stale_processing']));
        WP_CLI::line(sprintf('Failed: %d', $snapshot['failed']));
    }

    /**
     * Process queued Jooosi Mail messages.
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Maximum number of queued messages to process.
     * ---
     * default: 25
     * ---
     *
     * [--time-limit=<time-limit>]
     * : Maximum runtime in seconds.
     * ---
     * default: 20
     * ---
     *
     * ## EXAMPLES
     *
     *     # Process the default batch size.
     *     $ wp jooosi-mail queue:work
     *     Success: Processed 25 queue message(s).
     *
     *     # Process a smaller batch with a shorter runtime.
     *     $ wp jooosi-mail queue:work --limit=10 --time-limit=15
     *     Success: Processed 10 queue message(s).
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Process queued Jooosi Mail messages.')]
    public function work(array $args, array $assocArgs): void
    {
        $processed = $this->queueWorker->run(
            limit: max(1, (int) ($assocArgs['limit'] ?? 25)),
            timeLimit: max(5, (int) ($assocArgs['time-limit'] ?? 20)),
        );

        WP_CLI::success(sprintf('Processed %d queue message(s).', $processed));
    }

    /**
     * List failed Jooosi Mail queue messages.
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Maximum number of failed rows to show.
     * ---
     * default: 20
     * ---
     *
     * ## EXAMPLES
     *
     *     # List the most recent failed messages.
     *     $ wp jooosi-mail queue:failed
     *     id  queue  attempts  processed_at          error
     *     42  async  3         2026-03-23 09:15:00   Temporary connection failure
     *     41  async  5         2026-03-23 09:10:00   Provider rejected the message
     *
     *     # Limit the output to a single failed row.
     *     $ wp jooosi-mail queue:failed --limit=1
     *     id  queue  attempts  processed_at          error
     *     42  async  3         2026-03-23 09:15:00   Temporary connection failure
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'List failed Jooosi Mail queue messages.')]
    public function failed(array $args, array $assocArgs): void
    {
        $limit = max(1, (int) ($assocArgs['limit'] ?? 20));
        $rows = $this->failedMessageRepository->list($limit);

        if ($rows === []) {
            WP_CLI::success('No failed queue messages found.');

            return;
        }

        $items = array_map(fn (array $row): array => [
            'id' => (string) (int) $row['id'],
            'queue' => (string) ($row['queue_name'] ?? DatabaseTransport::NAME),
            'attempts' => (string) (int) ($row['attempt_count'] ?? 0),
            'processed_at' => (string) ($row['processed_at'] ?? ''),
            'error' => $this->formatError((string) ($row['last_error'] ?? '')),
        ], $rows);

        format_items('table', $items, ['id', 'queue', 'attempts', 'processed_at', 'error']);
    }

    /**
     * List processing Jooosi Mail queue messages.
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Maximum number of processing rows to show.
     * ---
     * default: 20
     * ---
     *
     * [--stale-after=<stale-after>]
     * : Age in seconds before a processing claim is considered stale.
     * ---
     * default: 300
     * ---
     *
     * [--stale-only=<stale-only>]
     * : Show only processing rows with stale claims.
     * ---
     * default: false
     * options:
     *   - 0
     *   - 1
     *   - false
     *   - true
     * ---
     *
     * ## EXAMPLES
     *
     *     # Inspect processing rows and their claim age.
     *     $ wp jooosi-mail queue:processing
     *     id  queue  attempts  claimed_at            claim_age  stale  error
     *     87  async  2         2026-03-23 09:10:00   180s       no     
     *
     *     # Show only stale claims older than 2 minutes.
     *     $ wp jooosi-mail queue:processing --stale-after=120 --stale-only=true
     *     id  queue  attempts  claimed_at            claim_age  stale  error
     *     81  async  1         2026-03-23 09:00:00   780s       yes    Connection timed out
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'List processing Jooosi Mail queue messages.')]
    public function processing(array $args, array $assocArgs): void
    {
        $limit = max(1, (int) ($assocArgs['limit'] ?? 20));
        $staleAfter = max(1, (int) ($assocArgs['stale-after'] ?? 300));
        $staleOnly = $this->resolveBoolean($assocArgs['stale-only'] ?? false);
        $rows = array_map(fn (array $row): array => $this->formatProcessingRow($row, $staleAfter), $this->queueMessageQuery->listProcessing($limit));

        if ($staleOnly) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['stale'] === 'yes'));
        }

        if ($rows === []) {
            WP_CLI::success($staleOnly ? 'No stale processing queue messages found.' : 'No processing queue messages found.');

            return;
        }

        format_items('table', $rows, ['id', 'queue', 'attempts', 'claimed_at', 'claim_age', 'stale', 'error']);
    }

    /**
     * Release stale Jooosi Mail queue claims.
     *
     * ## OPTIONS
     *
     * [--older-than=<older-than>]
     * : Age in seconds before a processing claim is released back to pending.
     * ---
     * default: 300
     * ---
     *
     * ## EXAMPLES
     *
     *     # Release claims older than 5 minutes.
     *     $ wp jooosi-mail queue:release-stale
     *     Success: Released 2 stale queue claim(s).
     *
     *     # Use a shorter stale threshold during incident response.
     *     $ wp jooosi-mail queue:release-stale --older-than=120
     *     Success: Released 1 stale queue claim(s).
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Release stale Jooosi Mail queue claims.')]
    public function releaseStale(array $args, array $assocArgs): void
    {
        $olderThan = max(1, (int) ($assocArgs['older-than'] ?? 300));
        $released = $this->queueMaintenanceService->releaseStaleClaims($olderThan);

        WP_CLI::success(sprintf('Released %d stale queue claim(s).', $released));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, queue: string, attempts: string, claimed_at: string, claim_age: string, stale: string, error: string}
     *
     * @since 0.1.0
     */
    private function formatProcessingRow(array $row, int $staleAfter): array
    {
        $claimedAt = (string) ($row['claimed_at'] ?? '');
        $claimedAtTimestamp = $claimedAt !== '' ? strtotime($claimedAt) : false;
        $claimAgeSeconds = is_int($claimedAtTimestamp) ? max(0, time() - $claimedAtTimestamp) : null;

        return [
            'id' => (string) (int) ($row['id'] ?? 0),
            'queue' => (string) ($row['queue_name'] ?? DatabaseTransport::NAME),
            'attempts' => (string) (int) ($row['attempt_count'] ?? 0),
            'claimed_at' => $claimedAt,
            'claim_age' => $claimAgeSeconds !== null ? sprintf('%ds', $claimAgeSeconds) : 'n/a',
            'stale' => $claimAgeSeconds !== null && $claimAgeSeconds >= $staleAfter ? 'yes' : 'no',
            'error' => $this->formatError((string) ($row['last_error'] ?? '')),
        ];
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
    private function resolveBoolean(mixed $value, bool $default = false): bool
    {
        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($resolved) ? $resolved : $default;
    }

    /**
     * Retry failed Jooosi Mail queue messages.
     *
     * ## OPTIONS
     *
     * [--id=<id>]
     * : Retry a specific failed message id. Omit to retry every failed message.
     *
     * ## EXAMPLES
     *
     *     # Retry every failed message.
     *     $ wp jooosi-mail queue:retry
     *     Success: Retried 3 failed message(s).
     *
     *     # Retry a single failed message.
     *     $ wp jooosi-mail queue:retry --id=42
     *     Success: Retried 1 failed message(s).
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Retry failed Jooosi Mail queue messages.')]
    public function retry(array $args, array $assocArgs): void
    {
        $messageId = isset($assocArgs['id']) ? (int) $assocArgs['id'] : null;
        $retried = $this->failedMessageService->retry($messageId);

        WP_CLI::success(sprintf('Retried %d failed message(s).', $retried));
    }
}

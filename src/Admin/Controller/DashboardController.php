<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Admin\Connection\AdminConnectionPayloadFactory;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Queue\Query\QueueMessageQuery;
use JooosiMail\Webhook\Event\WebhookEventRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves operational data for the admin dashboard.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/dashboard')]
final class DashboardController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
        private readonly OptionStore $optionStore,
        private readonly ConnectionStatusReporter $connectionStatusReporter,
        private readonly AdminConnectionPayloadFactory $connectionPayloadFactory,
        private readonly QueueMessageQuery $queueMessageQuery,
        private readonly MailAttemptRepository $mailAttemptRepository,
        private readonly WebhookEventRepository $webhookEventRepository,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $connectionSummary = $this->connectionStatusReporter->summarizeActiveConnections();
        $queueSummary = $this->queueMessageQuery->getStatusSnapshot();
        $mailSummary = $this->getMailLogSummary();
        $fromDateParam = $request->get_param('fromDate');
        $toDateParam = $request->get_param('toDate');
        $fromDate = is_string($fromDateParam) ? $this->normalizeLogDate($fromDateParam) : null;
        $toDate = is_string($toDateParam) ? $this->normalizeLogDate($toDateParam) : null;

        return new WP_REST_Response([
            'summary' => [
                'deliveryMode' => (string) $this->optionStore->get('settings.delivery.mode', 'async'),
                'routingStrategy' => (string) $this->optionStore->get('settings.delivery.strategy', 'weighted_random'),
                'interceptEnabled' => (bool) $this->optionStore->get('settings.mail.intercept.enabled', true),
                'connectionsTotal' => count($this->connectionStatusReporter->getStatuses(true)),
                'activeConnections' => (int) ($connectionSummary['active_connections'] ?? 0),
                'availableConnections' => (int) ($connectionSummary['available_connections'] ?? 0),
                'temporarilyUnavailableConnections' => (int) ($connectionSummary['temporarily_unavailable_connections'] ?? 0),
                'nextAvailableAt' => $this->normalizeDateTime($connectionSummary['next_available_at'] ?? null),
                'queuePendingReady' => (int) ($queueSummary['pending_ready'] ?? 0),
                'queuePendingDeferred' => (int) ($queueSummary['pending_deferred'] ?? 0),
                'queueProcessing' => (int) ($queueSummary['processing'] ?? 0),
                'queueStaleProcessing' => (int) ($queueSummary['stale_processing'] ?? 0),
                'queueFailed' => (int) ($queueSummary['failed'] ?? 0),
                'mailPending' => (int) ($mailSummary['pending'] ?? 0),
                'mailQueued' => (int) ($mailSummary['queued'] ?? 0),
                'mailProcessing' => (int) ($mailSummary['processing'] ?? 0),
                'mailSent' => (int) ($mailSummary['sent'] ?? 0),
                'mailFailed' => (int) ($mailSummary['failed'] ?? 0),
                'mailTotal' => (int) ($mailSummary['total'] ?? 0),
                'webhookEvents' => $this->getWebhookEventCount(),
            ],
            'connections' => $this->getConnectionPayloads(),
            'sendingStats' => $this->getSendingStats($fromDate, $toDate),
            'recentAttempts' => $this->normalizeAttempts($this->mailAttemptRepository->listRecent(8)),
            'recentWebhooks' => $this->normalizeWebhookEvents($this->webhookEventRepository->listRecent(8)),
            'failedMessages' => $this->normalizeQueueMessages($this->queueMessageQuery->listFailed(5)),
        ]);
    }

    /**
     * @return array<string, int>
     *
     * @since 0.1.0
     */
    private function getMailLogSummary(): array
    {
        $row = $this->connection->fetchAssociative(sprintf(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = :pending_status THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = :queued_status THEN 1 ELSE 0 END) AS queued,
                SUM(CASE WHEN status = :processing_status THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = :sent_status THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = :failed_status THEN 1 ELSE 0 END) AS failed
            FROM %s',
            $this->tableNameResolver->resolve('mail_logs'),
        ), [
            'pending_status' => 'pending',
            'queued_status' => 'queued',
            'processing_status' => 'processing',
            'sent_status' => 'sent',
            'failed_status' => 'failed',
        ]);

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'queued' => (int) ($row['queued'] ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'sent' => (int) ($row['sent'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
        ];
    }

    /**
     * @since 0.1.0
     */
    private function getWebhookEventCount(): int
    {
        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s', $this->tableNameResolver->resolve('webhook_events')),
        );
    }

    /**
     * @return list<array{date: string, total: int, sent: int, failed: int}>
     *
     * @since 0.1.0
     */
    private function getSendingStats(?string $fromDate, ?string $toDate): array
    {
        [$startDate, $endDate] = $this->resolveSendingStatsDateRange($fromDate, $toDate);
        $dateExpression = 'COALESCE(sent_at, queued_at, created_at, updated_at)';

        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT
                DATE(%1$s) AS stat_date,
                COUNT(*) AS total,
                SUM(CASE WHEN status = :sent_status THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = :failed_status THEN 1 ELSE 0 END) AS failed
            FROM %2$s
            WHERE %1$s >= :start_date
                AND %1$s <= :end_date
            GROUP BY DATE(%1$s)
            ORDER BY stat_date ASC',
            $dateExpression,
            $this->tableNameResolver->resolve('mail_logs'),
        ), [
            'sent_status' => 'sent',
            'failed_status' => 'failed',
            'start_date' => $startDate->format('Y-m-d 00:00:00'),
            'end_date' => $endDate->format('Y-m-d 23:59:59'),
        ]);

        $rowsByDate = [];

        foreach ($rows as $row) {
            $date = (string) ($row['stat_date'] ?? '');

            if ($date === '') {
                continue;
            }

            $rowsByDate[$date] = [
                'total' => (int) ($row['total'] ?? 0),
                'sent' => (int) ($row['sent'] ?? 0),
                'failed' => (int) ($row['failed'] ?? 0),
            ];
        }

        $stats = [];
        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate->add(new DateInterval('P1D')));

        foreach ($period as $date) {
            $dateKey = $date->format('Y-m-d');
            $row = $rowsByDate[$dateKey] ?? ['total' => 0, 'sent' => 0, 'failed' => 0];

            $stats[] = [
                'date' => $dateKey,
                'total' => $row['total'],
                'sent' => $row['sent'],
                'failed' => $row['failed'],
            ];
        }

        return $stats;
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     *
     * @since 0.1.0
     */
    private function resolveSendingStatsDateRange(?string $fromDate, ?string $toDate): array
    {
        $endDate = $toDate !== null
            ? new DateTimeImmutable($toDate . ' 00:00:00')
            : new DateTimeImmutable(gmdate('Y-m-d 00:00:00'));
        $startDate = $fromDate !== null
            ? new DateTimeImmutable($fromDate . ' 00:00:00')
            : $endDate->sub(new DateInterval('P89D'));

        if ($startDate > $endDate) {
            return [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }

    /**
     * @since 0.1.0
     */
    private function normalizeLogDate(string $value): ?string
    {
        $trimmedValue = trim($value);

        if ($trimmedValue === '' || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmedValue, $matches) !== 1) {
            return null;
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return $trimmedValue;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function getConnectionPayloads(): array
    {
        $payloads = [];

        foreach ($this->connectionStatusReporter->getStatuses(true) as $status) {
            $connection = $status['connection'];
            $availability = is_array($status['availability'] ?? null) ? $status['availability'] : [];
            $payload = $this->connectionPayloadFactory->createList($connection);
            $payload['healthScore'] = (int) ($status['health_score'] ?? 0);
            $payload['available'] = (bool) ($availability['available'] ?? false);
            $payload['unavailableReasons'] = array_values(array_map('strval', is_array($availability['unavailable_reasons'] ?? null) ? $availability['unavailable_reasons'] : []));
            $payload['nextAvailableAt'] = $this->normalizeDateTime($availability['next_available_at'] ?? null);
            $payload['rateLimit'] = $this->normalizeRateLimit(is_array($availability['rate_limit'] ?? null) ? $availability['rate_limit'] : []);
            $payload['circuitBreaker'] = $this->normalizeCircuitBreaker(is_array($availability['circuit_breaker'] ?? null) ? $availability['circuit_breaker'] : []);
            $payload['webhookUrl'] = $connection->id !== null ? rest_url('jooosi-mail/v1/webhook/' . $connection->id) : null;
            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * @param list<array<string, mixed>> $attempts
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeAttempts(array $attempts): array
    {
        return array_map(fn (array $attempt): array => [
            'id' => (int) ($attempt['id'] ?? 0),
            'mailLogId' => (int) ($attempt['mail_log_id'] ?? 0),
            'connectionId' => (int) ($attempt['connection_id'] ?? 0),
            'connectionName' => (string) ($attempt['connection_name'] ?? ''),
            'status' => (string) ($attempt['status'] ?? ''),
            'errorMessage' => isset($attempt['error_message']) ? (string) $attempt['error_message'] : null,
            'transportMessageId' => isset($attempt['transport_message_id']) ? (string) $attempt['transport_message_id'] : null,
            'startedAt' => isset($attempt['started_at']) ? (string) $attempt['started_at'] : null,
            'finishedAt' => isset($attempt['finished_at']) ? (string) $attempt['finished_at'] : null,
        ], $attempts);
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeWebhookEvents(array $events): array
    {
        return array_map(fn (array $event): array => [
            'id' => (int) ($event['id'] ?? 0),
            'connectionId' => isset($event['connection_id']) ? (int) $event['connection_id'] : null,
            'connectionName' => isset($event['connection_name']) ? (string) $event['connection_name'] : null,
            'mailLogId' => isset($event['mail_log_id']) ? (int) $event['mail_log_id'] : null,
            'eventType' => (string) ($event['event_type'] ?? ''),
            'transportMessageId' => isset($event['transport_message_id']) ? (string) $event['transport_message_id'] : null,
            'providerEventId' => isset($event['provider_event_id']) ? (string) $event['provider_event_id'] : null,
            'occurredAt' => isset($event['occurred_at']) ? (string) $event['occurred_at'] : null,
            'createdAt' => isset($event['created_at']) ? (string) $event['created_at'] : null,
        ], $events);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeQueueMessages(array $messages): array
    {
        return array_map(fn (array $message): array => [
            'id' => (int) ($message['id'] ?? 0),
            'status' => (string) ($message['status'] ?? ''),
            'priority' => (int) ($message['priority'] ?? 0),
            'attemptCount' => (int) ($message['attempt_count'] ?? 0),
            'maxAttempts' => (int) ($message['max_attempts'] ?? 0),
            'lastError' => isset($message['last_error']) ? (string) $message['last_error'] : null,
            'availableAt' => isset($message['available_at']) ? (string) $message['available_at'] : null,
            'claimedAt' => isset($message['claimed_at']) ? (string) $message['claimed_at'] : null,
            'processedAt' => isset($message['processed_at']) ? (string) $message['processed_at'] : null,
            'createdAt' => isset($message['created_at']) ? (string) $message['created_at'] : null,
            'updatedAt' => isset($message['updated_at']) ? (string) $message['updated_at'] : null,
        ], $messages);
    }

    /**
     * @param array<string, mixed> $rateLimit
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function normalizeRateLimit(array $rateLimit): array
    {
        $windows = [];

        foreach ($rateLimit['windows'] ?? [] as $period => $window) {
            if (! is_array($window)) {
                continue;
            }

            $windows[(string) $period] = [
                'limit' => (int) ($window['limit'] ?? 0),
                'count' => (int) ($window['count'] ?? 0),
                'remaining' => isset($window['remaining']) ? (int) $window['remaining'] : null,
                'windowStartedAt' => $this->normalizeDateTime($window['window_started_at'] ?? null),
                'windowEndsAt' => $this->normalizeDateTime($window['window_ends_at'] ?? null),
                'exhausted' => (bool) ($window['exhausted'] ?? false),
            ];
        }

        return [
            'blocked' => (bool) ($rateLimit['blocked'] ?? false),
            'windows' => $windows,
        ];
    }

    /**
     * @param array<string, mixed> $circuitBreaker
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function normalizeCircuitBreaker(array $circuitBreaker): array
    {
        return [
            'enabled' => (bool) ($circuitBreaker['enabled'] ?? false),
            'threshold' => (int) ($circuitBreaker['threshold'] ?? 0),
            'windowSeconds' => (int) ($circuitBreaker['window_seconds'] ?? 0),
            'cooldownSeconds' => (int) ($circuitBreaker['cooldown_seconds'] ?? 0),
            'recentFailures' => (int) ($circuitBreaker['recent_failures'] ?? 0),
            'blacklistedUntil' => $this->normalizeDateTime($circuitBreaker['blacklisted_until'] ?? null),
        ];
    }

    /**
     * @since 0.1.0
     */
    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return gmdate(DATE_ATOM, (int) $value);
        }

        return is_string($value) ? $value : null;
    }
}

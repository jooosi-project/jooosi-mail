<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Controller\Log;

use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Queue\Failure\FailedMessageRepository;
use JooosiMail\Queue\Message\SendEmailMessage;
use JooosiMail\Queue\Query\QueueMessageQuery;
use JooosiMail\Webhook\Event\WebhookEventRepository;
use JooosiMailDeps\Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;
/**
 * Serves admin log overview data.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/logs')]
final class OverviewController
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver, private readonly MailAttemptRepository $mailAttemptRepository, private readonly WebhookEventRepository $webhookEventRepository, private readonly FailedMessageRepository $failedMessageRepository, private readonly QueueMessageQuery $queueMessageQuery, private readonly SerializerInterface $serializer)
    {
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $queueSummary = $this->queueMessageQuery->getStatusSnapshot();
        return new WP_REST_Response(['summary' => ['mail' => $this->getMailLogSummary(), 'queue' => ['pendingReady' => (int) ($queueSummary['pending_ready'] ?? 0), 'pendingDeferred' => (int) ($queueSummary['pending_deferred'] ?? 0), 'processing' => (int) ($queueSummary['processing'] ?? 0), 'staleProcessing' => (int) ($queueSummary['stale_processing'] ?? 0), 'failed' => (int) ($queueSummary['failed'] ?? 0)], 'webhookEvents' => $this->getWebhookEventCount(), 'failedMessages' => $this->failedMessageRepository->count()], 'attempts' => $this->normalizeAttempts($this->mailAttemptRepository->listRecent(50)), 'events' => $this->normalizeWebhookEvents($this->webhookEventRepository->listRecent(50)), 'failedMessages' => $this->normalizeQueueMessages($this->failedMessageRepository->list(25)), 'processingMessages' => $this->normalizeQueueMessages($this->queueMessageQuery->listProcessing(25))]);
    }
    /**
     * @return array<string, int>
     *
     * @since 0.1.0
     */
    private function getMailLogSummary(): array
    {
        $row = $this->connection->fetchAssociative(sprintf('SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = :pending_status THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = :queued_status THEN 1 ELSE 0 END) AS queued,
                SUM(CASE WHEN status = :processing_status THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = :sent_status THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = :failed_status THEN 1 ELSE 0 END) AS failed
            FROM %s', $this->tableNameResolver->resolve('mail_logs')), ['pending_status' => 'pending', 'queued_status' => 'queued', 'processing_status' => 'processing', 'sent_status' => 'sent', 'failed_status' => 'failed']);
        return ['total' => (int) ($row['total'] ?? 0), 'pending' => (int) ($row['pending'] ?? 0), 'queued' => (int) ($row['queued'] ?? 0), 'processing' => (int) ($row['processing'] ?? 0), 'sent' => (int) ($row['sent'] ?? 0), 'failed' => (int) ($row['failed'] ?? 0)];
    }
    /**
     * @since 0.1.0
     */
    private function getWebhookEventCount(): int
    {
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $this->tableNameResolver->resolve('webhook_events')));
    }
    /**
     * @param list<array<string, mixed>> $attempts
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeAttempts(array $attempts): array
    {
        return array_map(fn(array $attempt): array => ['id' => (int) ($attempt['id'] ?? 0), 'mailLogId' => (int) ($attempt['mail_log_id'] ?? 0), 'connectionId' => (int) ($attempt['connection_id'] ?? 0), 'connectionName' => (string) ($attempt['connection_name'] ?? ''), 'status' => (string) ($attempt['status'] ?? ''), 'errorMessage' => isset($attempt['error_message']) ? (string) $attempt['error_message'] : null, 'transportMessageId' => isset($attempt['transport_message_id']) ? (string) $attempt['transport_message_id'] : null, 'startedAt' => isset($attempt['started_at']) ? (string) $attempt['started_at'] : null, 'finishedAt' => isset($attempt['finished_at']) ? (string) $attempt['finished_at'] : null], $attempts);
    }
    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeWebhookEvents(array $events): array
    {
        return array_map(fn(array $event): array => ['id' => (int) ($event['id'] ?? 0), 'connectionId' => isset($event['connection_id']) ? (int) $event['connection_id'] : null, 'connectionName' => isset($event['connection_name']) ? (string) $event['connection_name'] : null, 'mailLogId' => isset($event['mail_log_id']) ? (int) $event['mail_log_id'] : null, 'eventType' => (string) ($event['event_type'] ?? ''), 'transportMessageId' => isset($event['transport_message_id']) ? (string) $event['transport_message_id'] : null, 'providerEventId' => isset($event['provider_event_id']) ? (string) $event['provider_event_id'] : null, 'occurredAt' => isset($event['occurred_at']) ? (string) $event['occurred_at'] : null, 'createdAt' => isset($event['created_at']) ? (string) $event['created_at'] : null], $events);
    }
    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeQueueMessages(array $messages): array
    {
        return array_map(fn(array $message): array => ['id' => (int) ($message['id'] ?? 0), 'mailLogId' => $this->extractQueueMessageMailLogId($message['body'] ?? null), 'status' => (string) ($message['status'] ?? ''), 'priority' => (int) ($message['priority'] ?? 0), 'attemptCount' => (int) ($message['attempt_count'] ?? 0), 'maxAttempts' => (int) ($message['max_attempts'] ?? 0), 'lastError' => isset($message['last_error']) ? (string) $message['last_error'] : null, 'availableAt' => isset($message['available_at']) ? (string) $message['available_at'] : null, 'claimedAt' => isset($message['claimed_at']) ? (string) $message['claimed_at'] : null, 'processedAt' => isset($message['processed_at']) ? (string) $message['processed_at'] : null, 'createdAt' => isset($message['created_at']) ? (string) $message['created_at'] : null, 'updatedAt' => isset($message['updated_at']) ? (string) $message['updated_at'] : null], $messages);
    }
    /**
     * @since 0.1.0
     */
    private function extractQueueMessageMailLogId(mixed $body): ?int
    {
        if (!is_string($body) || trim($body) === '') {
            return null;
        }
        try {
            $envelope = $this->serializer->decode(['body' => $body, 'headers' => []]);
        } catch (Throwable) {
            return null;
        }
        $message = $envelope->getMessage();
        return $message instanceof SendEmailMessage ? $message->mailLogId : null;
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Admin\ReadModel;

use JooosiMail\Admin\ReadModel\Log\LogQuery;
use JooosiMail\Admin\ReadModel\Log\MailLogReadModel;
use JooosiMail\Admin\ReadModel\Log\QueueLogReadModel;
use JooosiMail\Admin\ReadModel\Log\WebhookLogReadModel;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Verifies admin log read-model projections, filters, and pagination contracts.
 *
 * @since 1.0.9
 */
final class LogReadModelContractTest extends JooosiMailIntegrationTestCase
{
    /**
 * @since 1.0.9
     */
    public function testMailReadModelProjectsPayloadAndClampsFilteredPagination(): void
    {
        $html = '<p>hello</p>';

        $this->db()->insert($this->tableNameResolver()->resolve('mail_logs'), [
            'source' => 'test',
            'subject' => 'Failed message',
            'recipients_json' => wp_json_encode([['address' => 'to@example.com']]),
            'payload_json' => wp_json_encode([
                'from' => [['address' => 'from@example.com']],
                'textBody' => $html,
                'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
            ]),
            'plan_json' => wp_json_encode([]),
            'status' => 'failed',
            'created_at' => '2026-04-08 10:00:00',
            'updated_at' => '2026-04-08 10:00:00',
        ]);
        $this->db()->insert($this->tableNameResolver()->resolve('mail_logs'), [
            'source' => 'test',
            'subject' => 'Sent message',
            'recipients_json' => wp_json_encode([['address' => 'other@example.com']]),
            'payload_json' => wp_json_encode([]),
            'plan_json' => wp_json_encode([]),
            'status' => 'sent',
            'created_at' => '2026-04-08 11:00:00',
            'updated_at' => '2026-04-08 11:00:00',
        ]);

        $page = $this->container()->get(MailLogReadModel::class)->search($this->query([
            'page' => 99,
            'perPage' => 1,
            'sortBy' => 'id',
            'sortDirection' => 'ASC',
            'statuses' => ['failed'],
            'connectionIds' => [],
        ]));

        self::assertSame([
            'page' => 1,
            'perPage' => 1,
            'total' => 1,
            'totalPages' => 1,
        ], $page->pagination());
        self::assertSame($html, $page->items()[0]['htmlBody']);
        self::assertNull($page->items()[0]['textBody']);
        self::assertSame('failed', $page->items()[0]['status']);
        self::assertSame([
            ['label' => 'Failed', 'value' => 'failed', 'count' => 1],
            ['label' => 'Sent', 'value' => 'sent', 'count' => 1],
        ], $page->filters()['statuses']);
    }

    /**
 * @since 1.0.9
     */
    public function testQueueAndWebhookReadModelsPreserveFilteredProjections(): void
    {
        $this->db()->insert($this->tableNameResolver()->resolve('queue_messages'), [
            'body' => '{}',
            'headers_json' => wp_json_encode([]),
            'status' => 'pending',
            'priority' => 10,
            'available_at' => '2026-04-08 10:00:00',
            'attempt_count' => 0,
            'max_attempts' => 3,
            'created_at' => '2026-04-08 10:00:00',
            'updated_at' => '2026-04-08 10:00:00',
        ]);
        $this->db()->insert($this->tableNameResolver()->resolve('queue_messages'), [
            'body' => '{}',
            'headers_json' => wp_json_encode([]),
            'status' => 'failed',
            'priority' => 20,
            'available_at' => '2026-04-08 11:00:00',
            'claimed_worker_id' => 'stale-worker:1234',
            'attempt_count' => 1,
            'max_attempts' => 3,
            'created_at' => '2026-04-08 11:00:00',
            'updated_at' => '2026-04-08 11:00:00',
        ]);
        $failedQueueMessageId = (int) $this->db()->lastInsertId();
        $this->db()->insert($this->tableNameResolver()->resolve('queue_message_attempts'), [
            'queue_message_id' => $failedQueueMessageId,
            'attempt_number' => 1,
            'claimed_by' => 'test-claim',
            'worker_id' => 'worker-host:1234',
            'outcome' => 'failed',
            'error_message' => 'Connection timed out.',
            'started_at' => '2026-04-08 11:00:00',
            'finished_at' => '2026-04-08 11:00:05',
        ]);

        $queuePage = $this->container()->get(QueueLogReadModel::class)->search($this->query([
            'statuses' => ['failed'],
            'sortBy' => 'priority',
            'sortDirection' => 'DESC',
        ]));

        self::assertCount(1, $queuePage->items());
        self::assertSame('failed', $queuePage->items()[0]['status']);
        self::assertNull($queuePage->items()[0]['workerId']);
        self::assertArrayHasKey('mailLogId', $queuePage->items()[0]);
        self::assertSame([
            [
                'sequenceNumber' => 1,
                'attemptNumber' => 1,
                'outcome' => 'failed',
                'workerId' => 'worker-host:1234',
                'errorMessage' => 'Connection timed out.',
                'retryDelaySeconds' => null,
                'startedAt' => '2026-04-08 11:00:00',
                'finishedAt' => '2026-04-08 11:00:05',
            ],
        ], $queuePage->items()[0]['attemptHistory']);
        self::assertSame([
            ['label' => 'Failed', 'value' => 'failed', 'count' => 1],
            ['label' => 'Pending', 'value' => 'pending', 'count' => 1],
        ], $queuePage->filters()['statuses']);

        $this->db()->insert($this->tableNameResolver()->resolve('connections'), [
            'profile_key' => 'postmark',
            'name' => 'Webhook connection',
            'created_at' => '2026-04-08 09:00:00',
            'updated_at' => '2026-04-08 09:00:00',
        ]);
        $webhookConnectionId = (int) $this->db()->lastInsertId();

        $this->db()->insert($this->tableNameResolver()->resolve('webhook_events'), [
            'event_type' => 'delivered',
            'payload_json' => wp_json_encode(['ok' => true]),
            'created_at' => '2026-04-08 10:00:00',
        ]);
        $this->db()->insert($this->tableNameResolver()->resolve('webhook_events'), [
            'connection_id' => $webhookConnectionId,
            'event_type' => 'bounced',
            'payload_json' => wp_json_encode(['reason' => 'blocked']),
            'created_at' => '2026-04-08 11:00:00',
        ]);

        $webhookPage = $this->container()->get(WebhookLogReadModel::class)->search($this->query([
            'eventTypes' => ['bounced'],
        ]));

        self::assertCount(1, $webhookPage->items());
        self::assertSame('bounced', $webhookPage->items()[0]['eventType']);
        self::assertSame($webhookConnectionId, $webhookPage->items()[0]['connectionId']);
        self::assertSame('Webhook connection', $webhookPage->items()[0]['connectionName']);
        self::assertSame('postmark', $webhookPage->items()[0]['connectionProfileKey']);
        self::assertSame(
            "{\n    \"reason\": \"blocked\"\n}",
            $webhookPage->items()[0]['payloadJson'],
        );
        self::assertSame([
            ['label' => 'Bounced', 'value' => 'bounced', 'count' => 1],
            ['label' => 'Delivered', 'value' => 'delivered', 'count' => 1],
        ], $webhookPage->filters()['eventTypes']);
    }

    /**
     * @since 1.0.9
     */
    public function testQueueReadModelProjectsWorkerIdentityOnlyForProcessingMessages(): void
    {
        $this->db()->insert($this->tableNameResolver()->resolve('queue_messages'), [
            'body' => '{}',
            'headers_json' => wp_json_encode([]),
            'status' => 'processing',
            'priority' => 10,
            'available_at' => '2026-04-08 10:00:00',
            'claimed_at' => '2026-04-08 10:00:00',
            'claimed_by' => 'claim-token',
            'claimed_worker_id' => 'worker-host:1234',
            'attempt_count' => 1,
            'max_attempts' => 3,
            'created_at' => '2026-04-08 10:00:00',
            'updated_at' => '2026-04-08 10:00:00',
        ]);

        $page = $this->container()->get(QueueLogReadModel::class)->search($this->query([
            'statuses' => ['processing'],
        ]));

        self::assertCount(1, $page->items());
        self::assertSame('worker-host:1234', $page->items()[0]['workerId']);
    }

    /**
     * @param array<string, mixed> $overrides
     *
 * @since 1.0.9
     */
    private function query(array $overrides = []): LogQuery
    {
        return LogQuery::fromArray(array_replace([
            'search' => '',
            'fromDate' => null,
            'toDate' => null,
            'page' => 1,
            'perPage' => 25,
            'sortBy' => 'dateTime',
            'sortDirection' => 'DESC',
            'statuses' => [],
            'connectionIds' => [],
            'eventTypes' => [],
        ], $overrides));
    }
}

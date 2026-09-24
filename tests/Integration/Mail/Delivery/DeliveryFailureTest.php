<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Delivery;

use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use Throwable;

/**
 * Covers delivery failure, failover, and queue retry behavior.
 *
 * @since 0.1.0
 */
final class DeliveryFailureTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testSyncDeliveryReturnsFalseAndLogsFailedAttemptWhenTransportThrows(): void
    {
        $connection = $this->createFailingSmtpConnection([
            'name' => 'Sync failing SMTP',
            'default' => true,
        ]);
        $failedConnections = [];
        $failedMails = [];
        $connectionFailureListener = static function (int $mailLogId, int $connectionId, Throwable $throwable) use (&$failedConnections): void {
            $failedConnections[] = [
                'mail_log_id' => $mailLogId,
                'connection_id' => $connectionId,
                'error' => $throwable->getMessage(),
            ];
        };
        $mailFailureListener = static function (int $mailLogId, string $error) use (&$failedMails): void {
            $failedMails[] = [
                'mail_log_id' => $mailLogId,
                'error' => $error,
            ];
        };

        add_action('a!jooosi-mail/mail:failed.connection', $connectionFailureListener, 10, 3);
        add_action('a!jooosi-mail/mail:failed', $mailFailureListener, 10, 2);

        try {
            $this->optionStore()->set('settings.delivery.mode', 'sync');
            $this->optionStore()->set('settings.delivery.strategy', 'single');

            $result = wp_mail('recipient@example.test', 'Sync failure subject', 'Sync failure body');
        } finally {
            remove_action('a!jooosi-mail/mail:failed.connection', $connectionFailureListener, 10);
            remove_action('a!jooosi-mail/mail:failed', $mailFailureListener, 10);
        }

        $mailLog = $this->latestRow('mail_logs');
        $attempt = $this->mailAttemptRepository()->listRecent(limit: 1, mailLogId: (int) ($mailLog['id'] ?? 0))[0] ?? null;

        self::assertFalse($result);
        self::assertIsArray($mailLog);
        self::assertSame('failed', $mailLog['status']);
        self::assertSame('No connection could deliver this message.', $mailLog['last_error']);
        self::assertIsArray($attempt);
        self::assertSame('failed', $attempt['status']);
        self::assertSame($connection->id, (int) $attempt['connection_id']);
        self::assertNotSame('', trim((string) ($attempt['error_message'] ?? '')));
        self::assertCount(1, $failedConnections);
        self::assertCount(1, $failedMails);
        self::assertSame((int) $mailLog['id'], $failedConnections[0]['mail_log_id']);
        self::assertSame($connection->id, $failedConnections[0]['connection_id']);
        self::assertSame((int) $mailLog['id'], $failedMails[0]['mail_log_id']);
        self::assertSame('No connection could deliver this message.', $failedMails[0]['error']);
    }

    /**
     * @since 0.1.0
     */
    public function testSyncDeliveryFailsOverToBackupConnectionAfterPrimaryFailure(): void
    {
        $primaryConnection = $this->createFailingSmtpConnection([
            'name' => 'Primary failing SMTP',
            'default' => true,
            'circuit_threshold' => 1,
            'circuit_window' => 300,
            'circuit_cooldown' => 300,
        ]);
        $backupConnection = $this->createNullConnection([
            'name' => 'Backup null transport',
            'default' => false,
        ]);

        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'failover');

        $result = wp_mail('recipient@example.test', 'Failover subject', 'Failover body');
        $mailLog = $this->latestRow('mail_logs');
        $attempts = $this->mailAttemptRepository()->listRecent(limit: 5, mailLogId: (int) ($mailLog['id'] ?? 0));
        $statuses = array_column($attempts, 'status');
        $connectionIds = array_map('intval', array_column($attempts, 'connection_id'));
        $circuitStatus = $this->circuitBreaker()->getStatus($primaryConnection);

        self::assertTrue($result);
        self::assertIsArray($mailLog);
        self::assertSame('sent', $mailLog['status']);
        self::assertSame($backupConnection->id, (int) $mailLog['final_connection_id']);
        self::assertCount(2, $attempts);
        self::assertContains('failed', $statuses);
        self::assertContains('sent', $statuses);
        self::assertContains($primaryConnection->id, $connectionIds);
        self::assertContains($backupConnection->id, $connectionIds);
        self::assertSame(1, $circuitStatus['recent_failures']);
        self::assertIsInt($circuitStatus['blacklisted_until']);
        self::assertGreaterThan(time(), $circuitStatus['blacklisted_until']);
    }

    /**
     * @since 0.1.0
     */
    public function testQueuedDeliveryRetriesThenMarksTheQueueMessageFailed(): void
    {
        $this->createFailingSmtpConnection([
            'name' => 'Async failing SMTP',
            'default' => true,
        ]);
        $retryEvents = [];
        $failedEvents = [];
        $retryListener = static function (mixed $envelope, Throwable $throwable, int $delaySeconds) use (&$retryEvents): void {
            $retryEvents[] = [
                'error' => $throwable->getMessage(),
                'delay' => $delaySeconds,
            ];
        };
        $failedListener = static function (mixed $envelope, Throwable $throwable) use (&$failedEvents): void {
            $failedEvents[] = $throwable->getMessage();
        };

        add_action('a!jooosi-mail/queue:message.retrying', $retryListener, 10, 3);
        add_action('a!jooosi-mail/queue:message.failed', $failedListener, 10, 2);

        try {
            $this->optionStore()->set('settings.delivery.mode', 'async');
            $this->optionStore()->set('settings.delivery.strategy', 'single');
            $this->optionStore()->set('settings.queue.retry.max_retries', 2);
            $this->optionStore()->set('settings.queue.retry.delay_seconds', 0);
            $this->optionStore()->set('settings.queue.retry.multiplier', 1);

            wp_mail('recipient@example.test', 'Async failure subject', 'Async failure body');

            $firstRunProcessed = $this->queueWorker()->run(limit: 5, timeLimit: 20);
            $afterFirstRun = $this->latestRow('queue_messages');
            $queueAttemptHistory = $this->db()->fetchAllAssociative(
                sprintf(
                    'SELECT attempt_number, outcome, error_message FROM %s WHERE queue_message_id = :queue_message_id ORDER BY id ASC',
                    $this->tableNameResolver()->resolve('queue_message_attempts'),
                ),
                ['queue_message_id' => (int) ($afterFirstRun['id'] ?? 0)],
            );
            $mailLog = $this->latestRow('mail_logs');
            $firstRunAttempts = $this->mailAttemptRepository()->listRecent(limit: 10, mailLogId: (int) ($mailLog['id'] ?? 0));

            $secondRunProcessed = $this->queueWorker()->run(limit: 5, timeLimit: 20);
            $afterSecondRun = $this->latestRow('queue_messages');
            $secondRunAttempts = $this->mailAttemptRepository()->listRecent(limit: 10, mailLogId: (int) ($mailLog['id'] ?? 0));
        } finally {
            remove_action('a!jooosi-mail/queue:message.retrying', $retryListener, 10);
            remove_action('a!jooosi-mail/queue:message.failed', $failedListener, 10);
        }

        self::assertSame(0, $firstRunProcessed);
        self::assertIsArray($afterFirstRun);
        self::assertSame('failed', $afterFirstRun['status']);
        self::assertSame(3, (int) $afterFirstRun['attempt_count']);
        self::assertStringContainsString('No connection could deliver this message.', (string) $afterFirstRun['last_error']);
        self::assertSame(['retrying', 'retrying', 'failed'], array_column($queueAttemptHistory, 'outcome'));
        self::assertSame([1, 2, 3], array_map('intval', array_column($queueAttemptHistory, 'attempt_number')));
        self::assertStringContainsString(
            'No connection could deliver this message.',
            (string) $queueAttemptHistory[0]['error_message'],
        );
        self::assertNotSame('', trim((string) ($afterFirstRun['processed_at'] ?? '')));
        self::assertIsArray($mailLog);
        self::assertSame('failed', $mailLog['status']);
        self::assertCount(3, $firstRunAttempts);
        self::assertSame(['failed', 'failed', 'failed'], array_column($firstRunAttempts, 'status'));

        self::assertSame(0, $secondRunProcessed);
        self::assertIsArray($afterSecondRun);
        self::assertSame('failed', $afterSecondRun['status']);
        self::assertSame(3, (int) $afterSecondRun['attempt_count']);
        self::assertNotSame('', trim((string) ($afterSecondRun['processed_at'] ?? '')));
        self::assertCount(3, $secondRunAttempts);
        self::assertSame(['failed', 'failed', 'failed'], array_column($secondRunAttempts, 'status'));
        self::assertCount(2, $retryEvents);
        self::assertSame(0, $retryEvents[0]['delay']);
        self::assertCount(1, $failedEvents);
        self::assertStringContainsString('No connection could deliver this message.', $failedEvents[0]);
    }

    /**
     * @since 0.1.0
     */
    public function testQueuedDeliveryKeepsMailLogQueuedWhileRetryIsPending(): void
    {
        $this->createFailingSmtpConnection([
            'name' => 'Async delayed failing SMTP',
            'default' => true,
        ]);

        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');
        $this->optionStore()->set('settings.queue.retry.max_retries', 1);
        $this->optionStore()->set('settings.queue.retry.delay_seconds', 60);
        $this->optionStore()->set('settings.queue.retry.multiplier', 1);

        wp_mail('recipient@example.test', 'Async delayed failure subject', 'Async delayed failure body');

        $processed = $this->queueWorker()->run(limit: 5, timeLimit: 20);
        $mailLog = $this->latestRow('mail_logs');
        $queueMessage = $this->latestRow('queue_messages');

        self::assertSame(0, $processed);
        self::assertIsArray($mailLog);
        self::assertSame('queued', $mailLog['status']);
        self::assertStringContainsString('No connection could deliver this message.', (string) $mailLog['last_error']);
        self::assertIsArray($queueMessage);
        self::assertSame('pending', $queueMessage['status']);
        self::assertSame(1, (int) $queueMessage['attempt_count']);
        self::assertSame('', (string) ($queueMessage['processed_at'] ?? ''));
    }

    /**
     * @since 0.1.0
     */
    public function testQueuedDeliveryReconcilesPreviousSentAttemptWithoutResending(): void
    {
        $connection = $this->createNullConnection();

        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Reconcile sent attempt subject', 'Reconcile body');

        $mailLog = $this->latestRow('mail_logs');

        self::assertIsArray($mailLog);

        $this->mailAttemptRepository()->record(
            mailLogId: (int) $mailLog['id'],
            connectionId: $connection->id ?? 0,
            status: 'sent',
            transportMessageId: 'provider-accepted-before-mark-sent',
        );

        $processed = $this->queueWorker()->run(limit: 5, timeLimit: 20);
        $updatedMailLog = $this->latestRow('mail_logs');
        $queueMessage = $this->latestRow('queue_messages');
        $attempts = $this->mailAttemptRepository()->listRecent(limit: 10, mailLogId: (int) $mailLog['id']);

        self::assertSame(1, $processed);
        self::assertIsArray($updatedMailLog);
        self::assertSame('sent', $updatedMailLog['status']);
        self::assertSame('provider-accepted-before-mark-sent', $updatedMailLog['transport_message_id']);
        self::assertIsArray($queueMessage);
        self::assertSame('completed', $queueMessage['status']);
        self::assertCount(1, $attempts);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @since 0.1.0
     */
    private function createFailingSmtpConnection(array $overrides = []): Connection
    {
        return $this->connectionManager()->create(array_replace([
            'profile' => 'smtp',
            'name' => 'Failing SMTP Connection',
            'dsn' => 'smtp://127.0.0.1:1?timeout=1',
            'default' => true,
            'priority' => 10,
            'weight' => 1,
        ], $overrides));
    }
}

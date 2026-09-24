<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Queue;

use JooosiMail\Queue\Trigger\ActionSchedulerTrigger;
use JooosiMail\Queue\State\QueueLease;
use JooosiMail\Queue\State\QueueLeaseService;
use JooosiMail\Queue\Worker\WorkerRunner;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Covers queue processing, retry, and stale-claim recovery.
 *
 * @since 0.1.0
 */
final class QueueIntegrationTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testQueueWakeupIsScheduledWhenMailIsQueued(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        $result = wp_mail('recipient@example.test', 'Wakeup subject', 'Wakeup body');
        $mailLog = $this->latestRow('mail_logs');
        $queueMessage = $this->latestRow('queue_messages');
        $pendingActions = as_get_scheduled_actions([
            'hook' => ActionSchedulerTrigger::RUN_HOOK,
            'group' => ActionSchedulerTrigger::GROUP,
            'status' => 'pending',
        ], 'ids');

        self::assertTrue($result);
        self::assertIsArray($mailLog);
        self::assertSame('queued', $mailLog['status']);
        self::assertIsArray($queueMessage);
        self::assertSame('pending', $queueMessage['status']);
        self::assertCount(1, $pendingActions);
        self::assertCount(1, $this->actionSchedulerWakeups());
    }

    /**
     * @since 0.1.0
     */
    public function testActionSchedulerHookProcessesQueuedMessages(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Queued subject', 'Queued body');
        do_action(ActionSchedulerTrigger::RUN_HOOK);

        $mailLog = $this->latestRow('mail_logs');
        $queueMessage = $this->latestRow('queue_messages');
        $queueAttempt = $this->latestRow('queue_message_attempts');
        $attempt = $this->mailAttemptRepository()->listRecent(limit: 1)[0] ?? null;

        self::assertIsArray($mailLog);
        self::assertSame('sent', $mailLog['status']);
        self::assertIsArray($queueMessage);
        self::assertSame('completed', $queueMessage['status']);
        self::assertIsArray($queueAttempt);
        self::assertSame((int) $queueMessage['id'], (int) $queueAttempt['queue_message_id']);
        self::assertSame(1, (int) $queueAttempt['attempt_number']);
        self::assertSame('completed', $queueAttempt['outcome']);
        self::assertNotEmpty($queueAttempt['worker_id']);
        self::assertNotSame('', (string) ($queueAttempt['finished_at'] ?? ''));
        self::assertIsArray($attempt);
        self::assertSame('sent', $attempt['status']);
    }

    /**
     * @since 0.1.0
     */
    public function testRecurringFallbackDoesNotBlockAnImmediateWakeup(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');
        $this->actionSchedulerTrigger()->scheduleRecurring();

        wp_mail('recipient@example.test', 'Immediate wakeup subject', 'Immediate wakeup body');

        $pendingWakeups = as_get_scheduled_actions([
            'hook' => ActionSchedulerTrigger::RUN_HOOK,
            'group' => ActionSchedulerTrigger::GROUP,
            'status' => 'pending',
        ], 'ids');

        self::assertCount(1, $pendingWakeups);
        self::assertNotFalse(as_next_scheduled_action(ActionSchedulerTrigger::RECURRING_HOOK, [], ActionSchedulerTrigger::GROUP));
    }

    /**
     * @since 0.1.0
     */
    public function testQueueWorkerReschedulesWhenConnectionsAreTemporarilyUnavailable(): void
    {
        $connection = $this->createNullConnection([
            'rate_limit_minute' => 1,
        ]);

        $this->rateLimiter()->recordSend($connection);
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Deferred subject', 'Deferred body');

        $processed = $this->queueWorker()->run(limit: 5, timeLimit: 20);
        $mailLog = $this->latestRow('mail_logs');
        $queueMessage = $this->latestRow('queue_messages');
        $snapshot = $this->queueMessageQuery()->getStatusSnapshot();

        self::assertSame(0, $processed);
        self::assertIsArray($mailLog);
        self::assertSame('queued', $mailLog['status']);
        self::assertIsArray($queueMessage);
        self::assertSame('pending', $queueMessage['status']);
        self::assertSame(1, (int) $queueMessage['attempt_count']);
        self::assertStringContainsString('temporarily unavailable', (string) $queueMessage['last_error']);
        self::assertSame(0, $snapshot['pending_ready']);
        self::assertSame(1, $snapshot['pending_deferred']);
    }

    /**
     * @since 0.1.0
     */
    public function testQueueWorkerDrainsMultipleBatchesWithinOneRun(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Batch subject 1', 'Batch body 1');
        wp_mail('recipient@example.test', 'Batch subject 2', 'Batch body 2');
        wp_mail('recipient@example.test', 'Batch subject 3', 'Batch body 3');

        $processed = $this->queueWorker()->run(limit: 1, timeLimit: 20);
        $status = $this->queueMessageQuery()->getStatusSnapshot();

        self::assertSame(3, $processed);
        self::assertSame(0, $status['pending_ready']);
        self::assertSame(0, $status['processing']);
        self::assertSame(3, $this->countRows('mail_attempts'));
        self::assertSame('completed', $this->latestRow('queue_messages')['status'] ?? null);
        self::assertSame('sent', $this->latestRow('mail_logs')['status'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testScheduledRunnerSkipsWhenTheRunnerLeaseIsActive(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Lease subject', 'Lease body');

        add_option(WorkerRunner::RUNNER_LEASE_OPTION, (string) (time() + 60), '', false);

        try {
            $processed = $this->workerRunner()->runScheduled(limit: 5, timeLimit: 20);
        } finally {
            delete_option(WorkerRunner::RUNNER_LEASE_OPTION);
        }

        self::assertSame(0, $processed);
        self::assertSame('pending', $this->latestRow('queue_messages')['status'] ?? null);
        self::assertSame('queued', $this->latestRow('mail_logs')['status'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testScheduledRunnerReclaimsAnExpiredLegacyRunnerLease(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Expired lease subject', 'Expired lease body');
        add_option(WorkerRunner::RUNNER_LEASE_OPTION, (string) (time() - 60), '', false);

        $processed = $this->workerRunner()->runScheduled(limit: 5, timeLimit: 20);

        self::assertSame(1, $processed);
        self::assertFalse(get_option(WorkerRunner::RUNNER_LEASE_OPTION, false));
        self::assertSame('completed', $this->latestRow('queue_messages')['status'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testLateRunnerLeaseReleaseCannotDeleteTheNewOwnerLease(): void
    {
        $leaseService = $this->container()->get(QueueLeaseService::class);
        $firstLease = $leaseService->acquire(WorkerRunner::RUNNER_LEASE_OPTION, -1);
        $secondLease = null;

        $leaseService->setBeforeConditionalDeleteHook(function () use ($leaseService, &$secondLease): void {
            $secondLease = $leaseService->acquire(WorkerRunner::RUNNER_LEASE_OPTION, 30);
        });

        self::assertInstanceOf(QueueLease::class, $firstLease);
        self::assertFalse($leaseService->release($firstLease));
        self::assertInstanceOf(QueueLease::class, $secondLease);
        self::assertNotSame($firstLease->ownerToken, $secondLease->ownerToken);

        $currentLease = get_option(WorkerRunner::RUNNER_LEASE_OPTION, null);

        self::assertIsArray($currentLease);
        self::assertSame($secondLease->ownerToken, $currentLease['owner_token'] ?? null);
        self::assertTrue($leaseService->release($secondLease));
        self::assertFalse(get_option(WorkerRunner::RUNNER_LEASE_OPTION, false));
    }

    /**
     * @since 0.1.0
     */
    public function testScheduledRunnerQueuesAContinuationWhenReadyMessagesRemain(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Continuation subject', 'Continuation body');

        as_unschedule_all_actions(ActionSchedulerTrigger::RUN_HOOK, [], ActionSchedulerTrigger::GROUP);

        $processed = $this->workerRunner()->runScheduled(limit: 5, timeLimit: 0);
        $pendingWakeups = as_get_scheduled_actions([
            'hook' => ActionSchedulerTrigger::RUN_HOOK,
            'group' => ActionSchedulerTrigger::GROUP,
            'status' => 'pending',
        ], 'ids');

        self::assertSame(0, $processed);
        self::assertSame('pending', $this->latestRow('queue_messages')['status'] ?? null);
        self::assertCount(1, $pendingWakeups);
    }

    /**
     * @since 0.1.0
     */
    public function testQueueWorkerReleasesStaleClaimsBeforeProcessing(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Stale claim subject', 'Stale claim body');

        $queueMessage = $this->latestRow('queue_messages');

        self::assertIsArray($queueMessage);

        $this->db()->update($this->tableNameResolver()->resolve('queue_messages'), [
            'status' => 'processing',
            'claimed_at' => gmdate('Y-m-d H:i:s', time() - 600),
            'claimed_by' => 'stale-claim',
            'updated_at' => gmdate('Y-m-d H:i:s', time() - 600),
        ], [
            'id' => (int) $queueMessage['id'],
        ]);

        $processed = $this->queueWorker()->run(limit: 5, timeLimit: 20);
        $updatedQueueMessage = $this->latestRow('queue_messages');
        $mailLog = $this->latestRow('mail_logs');

        self::assertSame(1, $processed);
        self::assertIsArray($updatedQueueMessage);
        self::assertSame('completed', $updatedQueueMessage['status']);
        self::assertIsArray($mailLog);
        self::assertSame('sent', $mailLog['status']);
    }

    /**
     * @since 0.1.0
     */
    public function testLateAckFromReleasedStaleClaimCannotOverwriteNewClaim(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Late ack subject', 'Late ack body');

        $firstEnvelope = $this->databaseReceiver()->receive(1)[0] ?? null;
        $firstClaim = $this->latestRow('queue_messages');

        self::assertNotNull($firstEnvelope);
        self::assertIsArray($firstClaim);
        self::assertNotEmpty($firstClaim['claimed_worker_id']);

        $this->db()->update($this->tableNameResolver()->resolve('queue_messages'), [
            'claimed_at' => gmdate('Y-m-d H:i:s', time() - 600),
            'updated_at' => gmdate('Y-m-d H:i:s', time() - 600),
        ], [
            'id' => (int) $firstClaim['id'],
        ]);

        $released = $this->queueMaintenanceService()->releaseStaleClaims(300);
        $releasedClaim = $this->latestRow('queue_messages');
        $secondEnvelope = $this->databaseReceiver()->receive(1)[0] ?? null;
        $secondClaim = $this->latestRow('queue_messages');

        self::assertSame(1, $released);
        self::assertIsArray($releasedClaim);
        self::assertNull($releasedClaim['claimed_worker_id']);
        self::assertNotNull($secondEnvelope);
        self::assertIsArray($secondClaim);
        self::assertSame('processing', $secondClaim['status']);
        self::assertNotSame($firstClaim['claimed_by'], $secondClaim['claimed_by']);
        self::assertSame($firstClaim['claimed_worker_id'], $secondClaim['claimed_worker_id']);

        self::assertFalse($this->databaseReceiver()->ackClaimed($firstEnvelope));
        $afterLateAck = $this->latestRow('queue_messages');

        self::assertIsArray($afterLateAck);
        self::assertSame('processing', $afterLateAck['status']);
        self::assertSame($secondClaim['claimed_by'], $afterLateAck['claimed_by']);

        self::assertTrue($this->databaseReceiver()->ackClaimed($secondEnvelope));

        $completedQueueMessage = $this->latestRow('queue_messages');
        self::assertSame('completed', $completedQueueMessage['status'] ?? null);
        self::assertNull($completedQueueMessage['claimed_worker_id'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testStaleClaimReleaseDoesNotConsumeAttemptBeforeDispatch(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Crash before dispatch subject', 'Crash body');

        $envelope = $this->databaseReceiver()->receive(1)[0] ?? null;
        $queueMessage = $this->latestRow('queue_messages');

        self::assertNotNull($envelope);
        self::assertIsArray($queueMessage);

        $this->db()->update($this->tableNameResolver()->resolve('queue_messages'), [
            'claimed_at' => gmdate('Y-m-d H:i:s', time() - 600),
            'updated_at' => gmdate('Y-m-d H:i:s', time() - 600),
        ], [
            'id' => (int) $queueMessage['id'],
        ]);

        $released = $this->queueMaintenanceService()->releaseStaleClaims(300);
        $releasedMessage = $this->latestRow('queue_messages');

        self::assertSame(1, $released);
        self::assertIsArray($releasedMessage);
        self::assertSame('pending', $releasedMessage['status']);
        self::assertSame(0, (int) $releasedMessage['attempt_count']);
    }

    /**
     * @since 0.1.0
     */
    public function testCorruptQueuePayloadIsFailedDuringReceive(): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->db()->insert($this->tableNameResolver()->resolve('queue_messages'), [
            'body' => 'not-a-serialized-envelope',
            'headers_json' => '{}',
            'queue_name' => 'async',
            'status' => 'pending',
            'priority' => 10,
            'available_at' => $now,
            'attempt_count' => 0,
            'max_attempts' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $envelopes = $this->databaseReceiver()->receive(1);
        $queueMessage = $this->latestRow('queue_messages');

        self::assertSame([], $envelopes);
        self::assertIsArray($queueMessage);
        self::assertSame('failed', $queueMessage['status']);
        self::assertSame(1, (int) $queueMessage['attempt_count']);
        self::assertNotSame('', trim((string) ($queueMessage['last_error'] ?? '')));
        $attempt = $this->latestRow('queue_message_attempts');
        self::assertIsArray($attempt);
        self::assertSame((int) $queueMessage['id'], (int) $attempt['queue_message_id']);
        self::assertSame('failed', $attempt['outcome']);
        self::assertSame($queueMessage['last_error'], $attempt['error_message']);
    }
}

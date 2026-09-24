<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Logging;

use JooosiMail\Mail\Logging\MailLogRetentionPolicy;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Covers email log retention and disabled logging cleanup.
 *
 * @since 0.1.0
 */
final class MailLogRetentionServiceTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testEmailLogsAreEnabledAndKeptForeverByDefault(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        $result = wp_mail('recipient@example.test', 'Default retention subject', 'Default retention body');
        $mailLog = $this->latestRow('mail_logs');

        self::assertTrue($result);
        self::assertIsArray($mailLog);

        $this->markMailLogOld((int) $mailLog['id']);

        $deleted = $this->retentionService()->pruneExpired();

        self::assertSame(0, $deleted);
        self::assertSame(1, $this->countRows('mail_logs'));
        self::assertSame(1, $this->countRows('mail_attempts'));
    }

    /**
     * @since 0.1.0
     */
    public function testDisabledSyncLoggingDeletesTerminalLogAndAttempts(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'single');
        $this->optionStore()->set(MailLogRetentionPolicy::ENABLED_PATH, false);

        $result = wp_mail('recipient@example.test', 'Disabled sync log subject', 'Disabled sync log body');

        self::assertTrue($result);
        self::assertSame(0, $this->countRows('mail_logs'));
        self::assertSame(0, $this->countRows('mail_attempts'));
    }

    /**
     * @since 0.1.0
     */
    public function testDisabledAsyncLoggingKeepsPayloadUntilDeliveryFinishes(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');
        $this->optionStore()->set(MailLogRetentionPolicy::ENABLED_PATH, false);

        $result = wp_mail('recipient@example.test', 'Disabled async log subject', 'Disabled async log body');

        self::assertTrue($result);
        self::assertSame(1, $this->countRows('mail_logs'));
        self::assertSame(0, $this->countRows('mail_attempts'));

        $processed = $this->queueWorker()->run(limit: 5, timeLimit: 20);
        $queueMessage = $this->latestRow('queue_messages');

        self::assertSame(1, $processed);
        self::assertSame(0, $this->countRows('mail_logs'));
        self::assertSame(0, $this->countRows('mail_attempts'));
        self::assertIsArray($queueMessage);
        self::assertSame('completed', $queueMessage['status']);
    }

    /**
     * @since 0.1.0
     */
    public function testRetentionDeletesOldTerminalLogsAndAttempts(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'single');
        $this->optionStore()->set(MailLogRetentionPolicy::RETENTION_DAYS_PATH, 1);

        $result = wp_mail('recipient@example.test', 'Expired log subject', 'Expired log body');
        $mailLog = $this->latestRow('mail_logs');

        self::assertTrue($result);
        self::assertIsArray($mailLog);
        self::assertSame(1, $this->countRows('mail_attempts'));

        $this->markMailLogOld((int) $mailLog['id']);

        $deleted = $this->retentionService()->pruneExpired();

        self::assertSame(1, $deleted);
        self::assertSame(0, $this->countRows('mail_logs'));
        self::assertSame(0, $this->countRows('mail_attempts'));
    }

    /**
     * @since 1.0.9
     */
    public function testRetentionPrunesOldTerminalQueueHistoryButKeepsPendingHistory(): void
    {
        $this->optionStore()->set(MailLogRetentionPolicy::RETENTION_DAYS_PATH, 1);
        $now = gmdate('Y-m-d H:i:s');
        $oldDate = gmdate('Y-m-d H:i:s', time() - (3 * 86400));

        $this->db()->insert($this->tableNameResolver()->resolve('queue_messages'), [
            'body' => '{}',
            'headers_json' => '{}',
            'queue_name' => 'async',
            'status' => 'completed',
            'priority' => 10,
            'available_at' => $oldDate,
            'attempt_count' => 1,
            'max_attempts' => 3,
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
            'processed_at' => $oldDate,
        ]);
        $completedQueueMessageId = (int) $this->db()->lastInsertId();
        $this->db()->insert($this->tableNameResolver()->resolve('queue_messages'), [
            'body' => '{}',
            'headers_json' => '{}',
            'queue_name' => 'async',
            'status' => 'pending',
            'priority' => 10,
            'available_at' => $now,
            'attempt_count' => 1,
            'max_attempts' => 3,
            'created_at' => $oldDate,
            'updated_at' => $now,
        ]);
        $pendingQueueMessageId = (int) $this->db()->lastInsertId();

        foreach ([
            [$completedQueueMessageId, 'failed'],
            [$pendingQueueMessageId, 'retrying'],
        ] as [$queueMessageId, $outcome]) {
            $this->db()->insert($this->tableNameResolver()->resolve('queue_message_attempts'), [
                'queue_message_id' => $queueMessageId,
                'attempt_number' => 1,
                'claimed_by' => wp_generate_uuid4(),
                'outcome' => $outcome,
                'error_message' => 'Temporary connection failure.',
                'started_at' => $oldDate,
                'finished_at' => $oldDate,
            ]);
        }

        $deleted = $this->retentionService()->pruneExpired();
        $remainingQueueAttempts = $this->db()->fetchAllAssociative(sprintf(
            'SELECT queue_message_id FROM %s ORDER BY id ASC',
            $this->tableNameResolver()->resolve('queue_message_attempts'),
        ));
        $remainingQueueMessageIds = array_map(
            static fn (array $attempt): int => (int) $attempt['queue_message_id'],
            $remainingQueueAttempts,
        );

        self::assertSame(1, $deleted);
        self::assertSame([$pendingQueueMessageId], $remainingQueueMessageIds);
    }

    /**
     * @since 0.1.0
     */
    private function retentionService(): MailLogRetentionService
    {
        return $this->container()->get(MailLogRetentionService::class);
    }

    /**
     * @since 0.1.0
     */
    private function markMailLogOld(int $mailLogId): void
    {
        $oldDate = gmdate('Y-m-d H:i:s', time() - (3 * 86400));

        $this->db()->update($this->tableNameResolver()->resolve('mail_logs'), [
            'created_at' => $oldDate,
            'sent_at' => $oldDate,
            'updated_at' => $oldDate,
        ], [
            'id' => $mailLogId,
        ]);
    }
}

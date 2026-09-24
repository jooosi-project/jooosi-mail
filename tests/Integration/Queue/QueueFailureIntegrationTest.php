<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Queue;

use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Queue\Logging\QueueAttemptRepository;
use JooosiMail\Queue\Message\SendEmailMessage;
use JooosiMail\Queue\Retry\RetryDecider;
use JooosiMail\Queue\Worker\QueueWorker;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use RuntimeException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

/**
 * Covers terminal failure persistence through Messenger and owned queue claims.
 *
 * @since 0.1.0
 */
final class QueueFailureIntegrationTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testWorkerRecordsTerminalErrorWhenRetriesAreDisabled(): void
    {
        $this->queueMail(maxRetries: 0);
        $worker = $this->workerWithHandler(static function (): void {
            throw new RuntimeException('Recipient address was rejected.');
        });

        self::assertSame(0, $worker->run(limit: 1, timeLimit: 20));

        $queueMessage = $this->latestRow('queue_messages');
        $mailLog = $this->latestRow('mail_logs');

        self::assertIsArray($queueMessage);
        self::assertIsArray($mailLog);
        self::assertSame('failed', $queueMessage['status']);
        self::assertSame(1, (int) $queueMessage['attempt_count']);
        self::assertStringContainsString('Recipient address was rejected.', (string) $queueMessage['last_error']);
        self::assertNotEmpty($queueMessage['processed_at']);
        self::assertSame('failed', $mailLog['status']);
        self::assertSame($queueMessage['last_error'], $mailLog['last_error']);
        $attempt = $this->db()->fetchAssociative(
            sprintf(
                'SELECT outcome, error_message FROM %s WHERE queue_message_id = :queue_message_id ORDER BY id DESC LIMIT 1',
                $this->tableNameResolver()->resolve('queue_message_attempts'),
            ),
            ['queue_message_id' => (int) $queueMessage['id']],
        );
        self::assertSame('failed', $attempt['outcome'] ?? null);
        self::assertSame('Recipient address was rejected.', $attempt['error_message'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testWorkerReplacesRetryErrorWithTheFinalFailure(): void
    {
        $this->queueMail(maxRetries: 1);
        $this->optionStore()->set('settings.queue.retry.delay_seconds', 0);
        $attempts = 0;
        $worker = $this->workerWithHandler(static function () use (&$attempts): void {
            ++$attempts;

            throw new RuntimeException($attempts === 1
                ? 'Temporary provider failure.'
                : 'Recipient address was rejected.');
        });

        self::assertSame(0, $worker->run(limit: 1, timeLimit: 20));

        $queueMessage = $this->latestRow('queue_messages');

        self::assertSame(2, $attempts);
        self::assertIsArray($queueMessage);
        self::assertSame('failed', $queueMessage['status']);
        self::assertSame(2, (int) $queueMessage['attempt_count']);
        self::assertStringContainsString('Recipient address was rejected.', (string) $queueMessage['last_error']);
        self::assertStringNotContainsString('Temporary provider failure.', (string) $queueMessage['last_error']);
        self::assertSame($queueMessage['last_error'], $this->latestRow('mail_logs')['last_error'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    public function testLateWorkerFailureCannotOverwriteANewProcessingClaim(): void
    {
        $this->queueMail(maxRetries: 0);
        $newClaim = null;
        $worker = $this->workerWithHandler(function () use (&$newClaim): void {
            $queueMessage = $this->latestRow('queue_messages');

            self::assertIsArray($queueMessage);

            $this->db()->update($this->tableNameResolver()->resolve('queue_messages'), [
                'claimed_at' => gmdate('Y-m-d H:i:s', time() - 600),
                'last_error' => 'Previous attempt failed.',
            ], ['id' => (int) $queueMessage['id']]);

            self::assertSame(1, $this->queueMaintenanceService()->releaseStaleClaims(300));
            self::assertCount(1, $this->databaseReceiver()->receive(1));
            $newClaim = $this->latestRow('queue_messages');

            throw new RuntimeException('Expired worker failure.');
        });

        self::assertSame(0, $worker->run(limit: 1, timeLimit: 20));

        $queueMessage = $this->latestRow('queue_messages');

        self::assertIsArray($newClaim);
        self::assertSame($newClaim, $queueMessage);
        self::assertSame('processing', $queueMessage['status']);
        self::assertSame('Previous attempt failed.', $queueMessage['last_error']);
        self::assertNull($queueMessage['processed_at']);
        $attempt = $this->db()->fetchAssociative(
            sprintf(
                'SELECT outcome FROM %s WHERE queue_message_id = :queue_message_id ORDER BY id DESC LIMIT 1',
                $this->tableNameResolver()->resolve('queue_message_attempts'),
            ),
            ['queue_message_id' => (int) $queueMessage['id']],
        );
        self::assertSame('interrupted', $attempt['outcome'] ?? null);
        self::assertSame('queued', $this->latestRow('mail_logs')['status'] ?? null);
    }

    /**
     * @since 0.1.0
     */
    private function queueMail(int $maxRetries): void
    {
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');
        $this->optionStore()->set('settings.queue.retry.max_retries', $maxRetries);

        self::assertTrue(wp_mail('recipient@example.test', 'Queue failure subject', 'Queue failure body'));
    }

    /**
     * @param callable(): void $handler
     *
     * @since 0.1.0
     */
    private function workerWithHandler(callable $handler): QueueWorker
    {
        $messageBus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            SendEmailMessage::class => [$handler],
        ]))]);

        return new QueueWorker(
            $this->databaseReceiver(),
            $this->container()->get(QueueAttemptRepository::class),
            $messageBus,
            $this->container()->get(RetryDecider::class),
            $this->mailLogRepository(),
            $this->container()->get(EventPublisherInterface::class),
            $this->queueMaintenanceService(),
            $this->container()->get(MailLogRetentionService::class),
        );
    }
}

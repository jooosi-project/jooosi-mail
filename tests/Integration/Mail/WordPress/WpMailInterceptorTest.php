<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\WordPress;

use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Delivery\DeliveryService;
use JooosiMail\Mail\Logging\MailLifecycleLogger;
use JooosiMail\Mail\Routing\RoutingPolicyResolver;
use JooosiMail\Mail\Submission\MailSubmissionService;
use JooosiMail\Mail\WordPress\WpMailInterceptor;
use JooosiMail\Mail\WordPress\WpMailPayloadNormalizer;
use JooosiMail\Queue\Transport\DatabaseTransport;
use JooosiMail\Queue\Trigger\ActionSchedulerTrigger;
use JooosiMail\Queue\Trigger\TriggerCoordinator;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Covers the WordPress `wp_mail()` interception flow.
 *
 * @since 0.1.0
 */
final class WpMailInterceptorTest extends JooosiMailIntegrationTestCase
{
    /**
     * @dataProvider preemptedDeliveryProvider
     *
     * @since 0.1.0
     */
    public function testEarlierPreemptionSkipsDelivery(string $mode, bool $preempt): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', $mode);
        $preemptMail = static fn (): bool => $preempt;

        add_filter('pre_wp_mail', $preemptMail, 10);

        try {
            $result = wp_mail('recipient@example.test', 'Already handled subject', 'Body');
        } finally {
            remove_filter('pre_wp_mail', $preemptMail, 10);
        }

        self::assertSame($preempt, $result);
        self::assertSame(0, $this->countRows('mail_logs'));
        self::assertSame(0, $this->countRows('mail_attempts'));
        self::assertSame(0, $this->countRows('queue_messages'));
        self::assertCount(0, $this->actionSchedulerWakeups());
    }

    /**
     * @return array<string, array{string, bool}>
     *
     * @since 0.1.0
     */
    public static function preemptedDeliveryProvider(): array
    {
        return [
            'sync already sent' => ['sync', true],
            'sync vetoed' => ['sync', false],
            'async already sent' => ['async', true],
            'async vetoed' => ['async', false],
        ];
    }

    /**
     * @since 0.1.0
     */
    public function testDisabledInterceptorLeavesPreWpMailUnchanged(): void
    {
        $this->optionStore()->set('settings.mail.intercept.enabled', false);

        $result = apply_filters('pre_wp_mail', null, [
            'to' => 'recipient@example.test',
            'subject' => 'Disabled interception',
            'message' => 'Body',
        ]);

        self::assertNotSame(true, $result);
        self::assertSame(0, $this->countRows('mail_logs'));
        self::assertSame(0, $this->countRows('queue_messages'));
        self::assertCount(0, $this->actionSchedulerWakeups());
    }

    /**
     * @since 0.1.0
     */
    public function testWpMailSyncSendsThroughTheConfiguredNullConnection(): void
    {
        $connection = $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        $result = wp_mail('recipient@example.test', 'Sync subject', 'Sync body');
        $mailLog = $this->latestRow('mail_logs');
        $attempts = $this->mailAttemptRepository()->listRecent(limit: 5, mailLogId: (int) ($mailLog['id'] ?? 0));
        $attempt = $attempts[0] ?? null;

        self::assertTrue($result);
        self::assertIsArray($mailLog);
        self::assertSame('Sync subject', $mailLog['subject']);
        self::assertSame('sent', $mailLog['status']);
        self::assertSame($connection->id, (int) $mailLog['final_connection_id']);
        self::assertIsArray($attempt);
        self::assertSame('sent', $attempt['status']);
        self::assertSame($connection->id, (int) $attempt['connection_id']);
        self::assertSame(0, $this->countRows('queue_messages'));
        self::assertCount(0, $this->actionSchedulerWakeups());
    }

    /**
     * @since 0.1.0
     */
    public function testWpMailAsyncQueuesTheMessage(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        $result = wp_mail('recipient@example.test', 'Async subject', 'Async body');
        $mailLog = $this->latestRow('mail_logs');
        $queueMessage = $this->latestRow('queue_messages');

        self::assertTrue($result);
        self::assertIsArray($mailLog);
        self::assertSame('Async subject', $mailLog['subject']);
        self::assertSame('queued', $mailLog['status']);
        self::assertIsArray($queueMessage);
        self::assertSame('pending', $queueMessage['status']);
        self::assertSame(DatabaseTransport::NAME, $queueMessage['queue_name']);
        self::assertSame(0, (int) $queueMessage['attempt_count']);
        self::assertSame(0, $this->countRows('mail_attempts'));
        self::assertCount(1, $this->actionSchedulerWakeups());
    }

    /**
     * @since 1.0.8
     */
    public function testAsyncQueueNotificationFollowsCommitAndWakeupAndPreservesScheduling(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $scheduledAt = time() + 300;
        $interceptor = $this->container()->get(WpMailInterceptor::class);
        $notifications = [];
        $queueListener = function (int $mailLogId, string $queueName) use (&$notifications, $interceptor): void {
            $notifications[] = [
                'mail_log_id' => $mailLogId,
                'queue_name' => $queueName,
                'transaction_active' => $this->db()->isTransactionActive(),
                'queue_message' => $this->latestRow('queue_messages'),
                'wakeups' => $this->actionSchedulerWakeups(),
                'nested_result' => $interceptor->intercept(null, [
                    'to' => 'recipient@example.test',
                    'subject' => 'Nested interception',
                    'message' => 'Body',
                ]),
            ];
        };

        add_action('a!jooosi-mail/mail:queued', $queueListener, 10, 2);

        try {
            $result = $interceptor->intercept(null, [
                'to' => 'recipient@example.test',
                'subject' => 'Scheduled subject',
                'message' => 'Scheduled body',
                'headers' => [
                    'X-Priority: high',
                    'X-Schedule-Time: ' . gmdate('c', $scheduledAt),
                ],
            ]);
        } finally {
            remove_action('a!jooosi-mail/mail:queued', $queueListener, 10);
        }

        $mailLog = $this->latestRow('mail_logs');

        self::assertTrue($result);
        self::assertIsArray($mailLog);
        self::assertCount(1, $notifications);
        self::assertSame((int) $mailLog['id'], $notifications[0]['mail_log_id']);
        self::assertSame(DatabaseTransport::NAME, $notifications[0]['queue_name']);
        self::assertFalse($notifications[0]['transaction_active']);
        self::assertCount(1, $notifications[0]['wakeups']);
        self::assertNull($notifications[0]['nested_result']);
        self::assertSame(1, $this->countRows('mail_logs'));
        self::assertSame(1, $this->countRows('queue_messages'));

        $queueMessage = $notifications[0]['queue_message'];

        self::assertIsArray($queueMessage);
        self::assertSame(1, (int) $queueMessage['priority']);
        self::assertGreaterThanOrEqual($scheduledAt, strtotime($queueMessage['available_at'] . ' UTC'));
        self::assertLessThanOrEqual($scheduledAt + 5, strtotime($queueMessage['available_at'] . ' UTC'));
    }

    /**
     * @since 1.0.9
     */
    public function testThrowingQueuedSubscriberCannotRejectCommittedMail(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');
        $subscriber = static function (): void {
            throw new RuntimeException('Simulated queued subscriber failure.');
        };
        $notificationFailures = [];
        $failureListener = static function (RuntimeException $throwable, int $mailLogId, string $queueName) use (&$notificationFailures): void {
            $notificationFailures[] = [$throwable->getMessage(), $mailLogId, $queueName];
        };

        add_action('a!jooosi-mail/mail:queued', $subscriber, 10, 2);
        add_action('a!jooosi-mail/mail:queued.notification-failed', $failureListener, 10, 3);

        try {
            $result = wp_mail('recipient@example.test', 'Committed subject', 'Committed body');
        } finally {
            remove_action('a!jooosi-mail/mail:queued', $subscriber, 10);
            remove_action('a!jooosi-mail/mail:queued.notification-failed', $failureListener, 10);
        }

        $mailLog = $this->latestRow('mail_logs');
        $queueMessage = $this->latestRow('queue_messages');

        self::assertTrue($result);
        self::assertIsArray($mailLog);
        self::assertSame('queued', $mailLog['status']);
        self::assertIsArray($queueMessage);
        self::assertSame('pending', $queueMessage['status']);
        self::assertSame([
            ['Simulated queued subscriber failure.', (int) $mailLog['id'], DatabaseTransport::NAME],
        ], $notificationFailures);
    }

    /**
     * @since 0.1.0
     */
    public function testWpMailAsyncCoalescesWakeupsAcrossBurstEnqueues(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'Burst subject 1', 'Burst body 1');
        wp_mail('recipient@example.test', 'Burst subject 2', 'Burst body 2');
        wp_mail('recipient@example.test', 'Burst subject 3', 'Burst body 3');

        $pendingActions = as_get_scheduled_actions([
            'hook' => ActionSchedulerTrigger::RUN_HOOK,
            'group' => ActionSchedulerTrigger::GROUP,
            'status' => 'pending',
        ], 'ids');

        self::assertSame(3, $this->countRows('queue_messages'));
        self::assertCount(1, $pendingActions);
        self::assertCount(1, $this->actionSchedulerWakeups());
    }

    /**
     * @since 0.1.0
     */
    public function testAsyncDispatchFailureRollsBackMailAndQueueRowsAndAllowsTheNextSubmission(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        $failingBus = new class($this->container()->get(MessageBusInterface::class)) implements MessageBusInterface {
            /**
             * @since 1.0.8
             */
            private bool $failNextDispatch = true;

            /**
             * @since 1.0.8
             */
            public function __construct(private readonly MessageBusInterface $messageBus)
            {
            }

            /**
             * @since 1.0.8
             */
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = $this->messageBus->dispatch($message, $stamps);

                if ($this->failNextDispatch) {
                    $this->failNextDispatch = false;

                    throw new RuntimeException('Simulated failure after queue persistence.');
                }

                return $envelope;
            }
        };

        $submissionService = new MailSubmissionService(
            $this->container()->get(RoutingPolicyResolver::class),
            $this->container()->get(MailLifecycleLogger::class),
            $this->container()->get(DeliveryService::class),
            $failingBus,
            $this->container()->get(TriggerCoordinator::class),
            $this->db(),
            $this->container()->get(EventPublisherInterface::class),
        );
        $interceptor = new WpMailInterceptor(
            $this->container()->get(WpMailPayloadNormalizer::class),
            $submissionService,
            $this->optionStore(),
            $this->container()->get(EventPublisherInterface::class),
        );

        $args = [
            'to' => 'recipient@example.test',
            'subject' => 'Rollback subject',
            'message' => 'Rollback body',
            'headers' => '',
            'attachments' => [],
        ];

        $result = $interceptor->intercept(null, $args);

        self::assertFalse($result);
        self::assertSame(0, $this->countRows('mail_logs'));
        self::assertSame(0, $this->countRows('queue_messages'));
        self::assertCount(0, $this->actionSchedulerWakeups());
        self::assertFalse($this->db()->isTransactionActive());

        self::assertTrue($interceptor->intercept(null, $args));
        self::assertSame(1, $this->countRows('mail_logs'));
        self::assertSame(1, $this->countRows('queue_messages'));
        self::assertCount(1, $this->actionSchedulerWakeups());
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Queue;

use JooosiMail\Queue\Message\SendEmailMessage;
use JooosiMail\Queue\Retry\RetryAfterException;
use JooosiMail\Queue\Retry\RetryDecider;
use JooosiMail\Queue\Stamp\DatabaseMessageStamp;
use JooosiMail\Queue\Transport\DatabaseTransport;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Throwable;

/**
 * Covers retry decisions for exceptions wrapped by Messenger handlers.
 *
 * @since 0.1.0
 */
final class RetryDeciderTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testHandlerRetryDelayOverridesConfiguredBackoff(): void
    {
        $this->optionStore()->set('settings.queue.retry.delay_seconds', 10);
        $envelope = $this->createEnvelope();
        $failure = $this->dispatchFailingHandlers($envelope, new RetryAfterException('Connection cooling down.', 240));
        $retryDecider = $this->container()->get(RetryDecider::class);

        self::assertTrue($retryDecider->shouldRetry($envelope, $failure));
        self::assertSame(240, $retryDecider->getDelaySeconds($envelope, $failure));
    }

    /**
     * @since 0.1.0
     */
    public function testAllUnrecoverableHandlerFailuresPreventRetry(): void
    {
        $envelope = $this->createEnvelope();
        $failure = $this->dispatchFailingHandlers(
            $envelope,
            new UnrecoverableMessageHandlingException('Invalid recipient.'),
            new UnrecoverableMessageHandlingException('Invalid payload.'),
        );

        self::assertFalse($this->container()->get(RetryDecider::class)->shouldRetry($envelope, $failure));
    }

    /**
     * @since 0.1.0
     */
    public function testMixedHandlerFailuresRemainEligibleForRetry(): void
    {
        $envelope = $this->createEnvelope();
        $failure = $this->dispatchFailingHandlers(
            $envelope,
            new UnrecoverableMessageHandlingException('Invalid recipient.'),
            new RuntimeException('Temporary connection failure.'),
        );

        self::assertTrue($this->container()->get(RetryDecider::class)->shouldRetry($envelope, $failure));
    }

    /**
     * @since 0.1.0
     */
    public function testNestedHandlerFailuresHonorTheLongestRetryDelay(): void
    {
        $envelope = $this->createEnvelope();
        $nestedFailure = $this->dispatchFailingHandlers($envelope, new RetryAfterException('Provider cooling down.', 240));
        $failure = $this->dispatchFailingHandlers(
            $envelope,
            new RetryAfterException('Rate limited.', 360),
            $nestedFailure,
        );

        self::assertSame(360, $this->container()->get(RetryDecider::class)->getDelaySeconds($envelope, $failure));
    }

    /**
     * @since 0.1.0
     */
    public function testExplicitRetryDelayDoesNotBypassAttemptLimit(): void
    {
        $envelope = $this->createEnvelope(attemptCount: 3);
        $failure = $this->dispatchFailingHandlers($envelope, new RetryAfterException('Rate limited.', 120));

        self::assertFalse($this->container()->get(RetryDecider::class)->shouldRetry($envelope, $failure));
    }

    /**
     * @since 0.1.0
     */
    public function testMissingRetryDelayUsesConfiguredBackoff(): void
    {
        $this->optionStore()->set('settings.queue.retry.delay_seconds', 15);
        $this->optionStore()->set('settings.queue.retry.multiplier', 2);
        $envelope = $this->createEnvelope(attemptCount: 2);
        $failure = $this->dispatchFailingHandlers($envelope, new RetryAfterException('Temporary failure.'));

        self::assertSame(30, $this->container()->get(RetryDecider::class)->getDelaySeconds($envelope, $failure));
    }

    /**
     * @since 0.1.0
     */
    private function createEnvelope(int $attemptCount = 1): Envelope
    {
        return new Envelope(new SendEmailMessage(1), [new DatabaseMessageStamp(
            messageId: 1,
            attemptCount: $attemptCount,
            maxAttempts: 3,
            queueName: DatabaseTransport::NAME,
            claimedBy: 'retry-test',
            workerId: 'retry-test-worker',
        )]);
    }

    /**
     * @since 0.1.0
     */
    private function dispatchFailingHandlers(Envelope $envelope, Throwable ...$failures): HandlerFailedException
    {
        $handlers = [];

        foreach ($failures as $index => $failure) {
            $handler = static function () use ($failure): void {
                throw $failure;
            };
            $handlers[] = new HandlerDescriptor($handler, ['alias' => 'failure-' . $index]);
        }

        $messageBus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            SendEmailMessage::class => $handlers,
        ]))]);

        try {
            $messageBus->dispatch($envelope);
        } catch (HandlerFailedException $failure) {
            self::assertCount(count($failures), $failure->getWrappedExceptions());

            return $failure;
        }

        self::fail('Expected Messenger to wrap the handler failures.');
    }
}

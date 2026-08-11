<?php

declare(strict_types=1);

namespace JooosiMail\Queue\Retry;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Stamp\DatabaseMessageStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Throwable;

/**
 * Applies retry rules to failed queue messages.
 *
 * @since 0.1.0
 */
#[Service]
final class RetryDecider
{
    public function __construct(
        private readonly RetryPolicy $retryPolicy,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function shouldRetry(Envelope $envelope, Throwable $throwable): bool
    {
        if ($throwable instanceof UnrecoverableMessageHandlingException) {
            return false;
        }

        $stamp = $envelope->last(DatabaseMessageStamp::class);

        if (! $stamp instanceof DatabaseMessageStamp) {
            return false;
        }

        return $this->retryPolicy->shouldRetry($stamp->attemptCount, $stamp->maxAttempts);
    }

    /**
     * @since 0.1.0
     */
    public function getDelaySeconds(Envelope $envelope, ?Throwable $throwable = null): int
    {
        if ($throwable instanceof RetryDelayAwareExceptionInterface) {
            $retryAfterSeconds = $throwable->getRetryAfterSeconds();

            if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
                return $retryAfterSeconds;
            }
        }

        $stamp = $envelope->last(DatabaseMessageStamp::class);

        if (! $stamp instanceof DatabaseMessageStamp) {
            return 0;
        }

        return $this->retryPolicy->getDelaySeconds($stamp->attemptCount);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Retry;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Queue\Stamp\DatabaseMessageStamp;
use JooosiMailDeps\Symfony\Component\Messenger\Envelope;
use JooosiMailDeps\Symfony\Component\Messenger\Exception\HandlerFailedException;
use JooosiMailDeps\Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Throwable;
/**
 * Applies retry rules to failed queue messages.
 *
 * @since 0.1.0
 */
#[Service]
final class RetryDecider
{
    public function __construct(private readonly \JooosiMail\Queue\Retry\RetryPolicy $retryPolicy)
    {
    }
    /**
     * @since 0.1.0
     */
    public function shouldRetry(Envelope $envelope, Throwable $throwable): bool
    {
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return \false;
        }
        foreach ($this->unwrapFailures($throwable) as $failure) {
            if (!$failure instanceof UnrecoverableExceptionInterface) {
                return $this->retryPolicy->shouldRetry($stamp->attemptCount, $stamp->maxAttempts);
            }
        }
        return \false;
    }
    /**
     * @since 0.1.0
     */
    public function getDelaySeconds(Envelope $envelope, ?Throwable $throwable = null): int
    {
        $retryAfterSeconds = 0;
        foreach ($throwable === null ? [] : $this->unwrapFailures($throwable) as $failure) {
            if ($failure instanceof \JooosiMail\Queue\Retry\RetryDelayAwareExceptionInterface) {
                $retryAfterSeconds = max($retryAfterSeconds, $failure->getRetryAfterSeconds() ?? 0);
            }
        }
        if ($retryAfterSeconds > 0) {
            return $retryAfterSeconds;
        }
        $stamp = $envelope->last(DatabaseMessageStamp::class);
        if (!$stamp instanceof DatabaseMessageStamp) {
            return 0;
        }
        return $this->retryPolicy->getDelaySeconds($stamp->attemptCount);
    }
    /**
     * @return list<Throwable>
     *
     * @since 0.1.0
     */
    private function unwrapFailures(Throwable $throwable): array
    {
        if (!$throwable instanceof HandlerFailedException) {
            return [$throwable];
        }
        $failures = [];
        foreach ($throwable->getWrappedExceptions() as $failure) {
            array_push($failures, ...$this->unwrapFailures($failure));
        }
        return $failures;
    }
}

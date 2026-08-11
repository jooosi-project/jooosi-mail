<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Retry;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\WordPress\OptionStore;
/**
 * Configurable retry policy for queue workers.
 *
 * @since 0.1.0
 */
#[Service]
final class RetryPolicy
{
    public function __construct(private readonly OptionStore $optionStore)
    {
    }
    /**
     * @since 0.1.0
     */
    public function shouldRetry(int $attemptCount, ?int $maxAttempts = null): bool
    {
        $resolvedMaxAttempts = $maxAttempts ?? (int) $this->optionStore->get('settings.queue.retry.max_retries', 3) + 1;
        return $attemptCount < max(1, $resolvedMaxAttempts);
    }
    /**
     * @since 0.1.0
     */
    public function getDelaySeconds(int $attemptCount): int
    {
        $baseDelay = (int) $this->optionStore->get('settings.queue.retry.delay_seconds', 60);
        $multiplier = (int) $this->optionStore->get('settings.queue.retry.multiplier', 2);
        $maxDelay = (int) $this->optionStore->get('settings.queue.retry.max_delay_seconds', 900);
        $delay = $baseDelay * $multiplier ** max(0, $attemptCount - 1);
        return min($delay, $maxDelay);
    }
}

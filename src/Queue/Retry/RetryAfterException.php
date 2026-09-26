<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Retry;

use Override;
use RuntimeException;
use Throwable;
/**
 * Recoverable queue failure with an explicit retry-after delay.
 *
 * @since 0.1.0
 */
final class RetryAfterException extends RuntimeException implements \JooosiMail\Queue\Retry\RetryDelayAwareExceptionInterface
{
    public function __construct(string $message, private readonly ?int $retryAfterSeconds = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    /**
     * @since 0.1.0
     */
    #[Override]
    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}

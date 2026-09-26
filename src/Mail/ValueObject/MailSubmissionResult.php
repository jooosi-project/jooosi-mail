<?php

declare (strict_types=1);
namespace JooosiMail\Mail\ValueObject;

/**
 * Result of submitting a normalized email for delivery.
 *
 * @since 1.0.9
 */
final class MailSubmissionResult
{
    /**
     * @since 1.0.9
     */
    public function __construct(public readonly int $mailLogId, public readonly bool $accepted, public readonly ?string $error = null)
    {
    }
}

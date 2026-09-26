<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Mail;

/**
 * Result of sending an admin test email.
 *
 * @since 1.0.9
 */
final class TestEmailResult
{
    /**
     * @since 1.0.9
     */
    public function __construct(public readonly bool $sent, public readonly ?string $errorMessage = null)
    {
    }
}

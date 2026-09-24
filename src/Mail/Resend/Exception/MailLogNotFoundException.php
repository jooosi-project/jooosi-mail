<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Resend\Exception;

use RuntimeException;

/**
 * Raised when a requested source mail log no longer exists.
 *
 * @since 1.0.9
 */
final class MailLogNotFoundException extends RuntimeException
{
}

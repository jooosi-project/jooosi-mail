<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Resend\Exception;

use RuntimeException;
/**
 * Raised when a reconstructed email cannot be submitted again.
 *
 * @since 1.0.9
 */
final class MailResendException extends RuntimeException
{
}

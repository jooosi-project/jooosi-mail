<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Resend\Exception;

use RuntimeException;

/**
 * Raised when a retained mail log cannot be reconstructed for resending.
 *
 * @since 1.0.9
 */
final class InvalidMailLogPayloadException extends RuntimeException
{
}

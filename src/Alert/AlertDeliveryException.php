<?php

declare(strict_types=1);

namespace JooosiMail\Alert;

use RuntimeException;

/**
 * Reports a failed notification delivery without exposing channel credentials.
 *
 * @since 1.0.12
 */
final class AlertDeliveryException extends RuntimeException
{
}

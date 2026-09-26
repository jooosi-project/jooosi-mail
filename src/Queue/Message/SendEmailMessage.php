<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Message;

/**
 * Queue message pointing to a persisted mail log.
 *
 * @since 0.1.0
 */
final class SendEmailMessage
{
    public function __construct(public readonly int $mailLogId)
    {
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Handler;

use JooosiMail\Discovery\Attribute\MessageHandler;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Delivery\DeliveryService;
use JooosiMail\Queue\Message\SendEmailMessage;
use JooosiMail\Queue\Retry\RetryAfterException;
use JooosiMail\Queue\Transport\DatabaseTransport;
use RuntimeException;
/**
 * Delivers queued email logs.
 *
 * @since 0.1.0
 */
#[Service]
#[MessageHandler(SendEmailMessage::class, DatabaseTransport::NAME)]
final class SendEmailHandler
{
    public function __construct(private readonly DeliveryService $deliveryService)
    {
    }
    /**
     * @since 0.1.0
     */
    public function __invoke(SendEmailMessage $message): void
    {
        $result = $this->deliveryService->deliver($message->mailLogId, finalizeFailures: \false);
        if (!$result->successful) {
            if ($result->temporaryFailure) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new RetryAfterException(esc_html($result->error) ?? 'Queued mail delivery is temporarily unavailable.', $result->retryAfterSeconds);
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(esc_html($result->error) ?? 'Queued mail delivery failed.');
        }
    }
}

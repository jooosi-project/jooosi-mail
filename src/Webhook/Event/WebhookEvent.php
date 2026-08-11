<?php

declare(strict_types=1);

namespace JooosiMail\Webhook\Event;

/**
 * Normalized webhook event payload.
 *
 * @since 0.1.0
 */
final class WebhookEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly ?int $connectionId,
        public readonly ?int $mailLogId,
        public readonly string $eventType,
        public readonly ?string $transportMessageId,
        public readonly ?string $providerEventId,
        public readonly array $payload,
        public readonly ?string $occurredAt,
    ) {
    }
}

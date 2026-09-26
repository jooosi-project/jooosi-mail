<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Application;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Webhook\Event\WebhookEvent;
use JooosiMail\Webhook\Event\WebhookEventProjector;
use JooosiMail\Webhook\Event\WebhookEventRepository;
/**
 * Builds, persists, and projects normalized webhook events.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookEventPersistenceService
{
    public function __construct(private readonly \JooosiMail\Webhook\Application\WebhookMailLogCorrelator $mailLogCorrelator, private readonly WebhookEventRepository $webhookEventRepository, private readonly WebhookEventProjector $webhookEventProjector)
    {
    }
    /**
     * @param array<string, mixed> $event
     *
     * @since 1.0.9
     */
    public function persist(int $connectionId, array $event): WebhookEvent
    {
        $webhookEvent = new WebhookEvent(connectionId: $connectionId, mailLogId: $this->mailLogCorrelator->correlate($connectionId, $event), eventType: (string) ($event['event_type'] ?? 'received'), transportMessageId: $this->mailLogCorrelator->normalizeIdentifier($event['transport_message_id'] ?? null), providerEventId: $this->mailLogCorrelator->normalizeIdentifier($event['provider_event_id'] ?? null), payload: is_array($event['payload'] ?? null) ? $event['payload'] : [], occurredAt: isset($event['occurred_at']) ? (string) $event['occurred_at'] : gmdate('Y-m-d H:i:s'));
        $this->webhookEventRepository->save($webhookEvent);
        $this->webhookEventProjector->project($webhookEvent);
        return $webhookEvent;
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Webhook\Application;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use WP_REST_Request;

/**
 * Orchestrates parsing and persistence of provider webhook events.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookIngestionService
{
    public function __construct(
        private readonly WebhookEventParser $eventParser,
        private readonly WebhookEventPersistenceService $eventPersistence,
    ) {
    }

    /**
     * @since 1.0.9
     */
    public function ingest(WP_REST_Request $request, int $connectionId, Connection $connection): void
    {
        foreach ($this->eventParser->parse($request, $connection) as $event) {
            $this->eventPersistence->persist($connectionId, $event);
        }
    }
}

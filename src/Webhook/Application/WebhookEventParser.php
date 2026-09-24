<?php

declare(strict_types=1);

namespace JooosiMail\Webhook\Application;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Webhook\Adapter\WebhookAdapterRegistry;
use WP_REST_Request;

/**
 * Resolves a provider adapter and parses its request into normalized events.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookEventParser
{
    public function __construct(
        private readonly WebhookAdapterRegistry $webhookAdapterRegistry,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function parse(WP_REST_Request $request, Connection $connection): array
    {
        return $this->webhookAdapterRegistry->resolve($connection)->parse($request, $connection);
    }
}

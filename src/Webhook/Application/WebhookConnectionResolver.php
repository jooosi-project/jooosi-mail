<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Application;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionRepository;
use WP_REST_Request;
/**
 * Resolves the connection addressed by a webhook request.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookConnectionResolver
{
    public function __construct(private readonly ConnectionRepository $connectionRepository)
    {
    }
    /**
     * @since 1.0.9
     */
    public function fromRequest(WP_REST_Request $request): ?Connection
    {
        return $this->find((int) $request->get_param('connection_id'));
    }
    /**
     * @since 1.0.9
     */
    public function find(int $connectionId): ?Connection
    {
        if ($connectionId <= 0) {
            return null;
        }
        return $this->connectionRepository->find($connectionId);
    }
}

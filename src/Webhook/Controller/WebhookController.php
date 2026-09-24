<?php

declare(strict_types=1);

namespace JooosiMail\Webhook\Controller;

use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Connection\ConnectionRepository;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Webhook\Application\WebhookAuthorizationService;
use JooosiMail\Webhook\Application\WebhookConnectionResolver;
use JooosiMail\Webhook\Application\WebhookEventParser;
use JooosiMail\Webhook\Application\WebhookEventPersistenceService;
use JooosiMail\Webhook\Application\WebhookIngestionService;
use JooosiMail\Webhook\Application\WebhookMailLogCorrelator;
use JooosiMail\Webhook\Adapter\WebhookAdapterRegistry;
use JooosiMail\Webhook\Event\WebhookEventProjector;
use JooosiMail\Webhook\Event\WebhookEventRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Generic webhook ingestion endpoint.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'webhook')]
final class WebhookController
{
    private readonly WebhookConnectionResolver $connectionResolver;

    private readonly WebhookAuthorizationService $authorizationService;

    private readonly WebhookIngestionService $ingestionService;

    public function __construct(
        ConnectionRepository $connectionRepository,
        MailLogRepository $mailLogRepository,
        MailAttemptRepository $mailAttemptRepository,
        WebhookEventRepository $webhookEventRepository,
        WebhookEventProjector $webhookEventProjector,
        WebhookAdapterRegistry $webhookAdapterRegistry,
        EventPublisherInterface $eventPublisher,
        ?WebhookConnectionResolver $connectionResolver = null,
        ?WebhookAuthorizationService $authorizationService = null,
        ?WebhookIngestionService $ingestionService = null,
    ) {
        $this->connectionResolver = $connectionResolver ?? new WebhookConnectionResolver($connectionRepository);
        $this->authorizationService = $authorizationService ?? new WebhookAuthorizationService(
            $this->connectionResolver,
            $webhookAdapterRegistry,
            $eventPublisher,
        );
        $this->ingestionService = $ingestionService ?? new WebhookIngestionService(
            new WebhookEventParser($webhookAdapterRegistry),
            new WebhookEventPersistenceService(
                new WebhookMailLogCorrelator($mailAttemptRepository, $mailLogRepository),
                $webhookEventRepository,
                $webhookEventProjector,
            ),
        );
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)', methods: 'POST', permissionCallback: 'authorizeHandle')]
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $connectionId = (int) $request->get_param('connection_id');
        $connection = $this->connectionResolver->find($connectionId);

        if ($connection === null) {
            return new WP_REST_Response(['error' => 'Connection not found.'], 404);
        }

        $this->ingestionService->ingest($request, $connectionId, $connection);

        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * @since 0.1.0
     */
    public function authorizeHandle(WP_REST_Request $request): bool|WP_Error
    {
        return $this->authorizationService->authorize($request);
    }
}

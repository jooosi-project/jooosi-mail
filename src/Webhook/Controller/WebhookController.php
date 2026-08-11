<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Controller;

use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionRepository;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Webhook\Adapter\WebhookAdapterRegistry;
use JooosiMail\Webhook\Event\WebhookEvent;
use JooosiMail\Webhook\Event\WebhookEventProjector;
use JooosiMail\Webhook\Event\WebhookEventRepository;
use RuntimeException;
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
    public function __construct(private readonly ConnectionRepository $connectionRepository, private readonly MailLogRepository $mailLogRepository, private readonly MailAttemptRepository $mailAttemptRepository, private readonly WebhookEventRepository $webhookEventRepository, private readonly WebhookEventProjector $webhookEventProjector, private readonly WebhookAdapterRegistry $webhookAdapterRegistry, private readonly EventPublisherInterface $eventPublisher)
    {
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)', methods: 'POST', permissionCallback: 'authorizeHandle')]
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $connectionId = (int) $request->get_param('connection_id');
        $connection = $this->connectionRepository->find($connectionId);
        if ($connection === null) {
            return new WP_REST_Response(['error' => 'Connection not found.'], 404);
        }
        $webhookAdapter = $this->webhookAdapterRegistry->resolve($connection);
        $events = $webhookAdapter->parse($request, $connection);
        foreach ($events as $event) {
            $transportMessageId = is_scalar($event['transport_message_id'] ?? null) && trim((string) $event['transport_message_id']) !== '' ? (string) $event['transport_message_id'] : null;
            $providerEventId = is_scalar($event['provider_event_id'] ?? null) && trim((string) $event['provider_event_id']) !== '' ? (string) $event['provider_event_id'] : null;
            $mailLogId = isset($event['mail_log_id']) ? (int) $event['mail_log_id'] : null;
            if ($mailLogId === null && $transportMessageId !== null) {
                $mailLogId = $this->mailAttemptRepository->findMailLogIdByTransportMessageId($connectionId, $transportMessageId) ?? $this->mailLogRepository->findIdByTransportMessageId($transportMessageId, $connectionId);
            }
            $webhookEvent = new WebhookEvent(connectionId: $connectionId, mailLogId: $mailLogId, eventType: (string) ($event['event_type'] ?? 'received'), transportMessageId: $transportMessageId, providerEventId: $providerEventId, payload: is_array($event['payload'] ?? null) ? $event['payload'] : [], occurredAt: isset($event['occurred_at']) ? (string) $event['occurred_at'] : gmdate('Y-m-d H:i:s'));
            $this->webhookEventRepository->save($webhookEvent);
            $this->webhookEventProjector->project($webhookEvent);
        }
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
    /**
     * @since 0.1.0
     */
    public function authorizeHandle(WP_REST_Request $request): bool|WP_Error
    {
        $connection = $this->resolveWebhookConnection($request);
        if (!$connection instanceof Connection) {
            return new WP_Error('jooosi_mail_webhook_connection_not_found', 'Connection not found.', ['status' => 404]);
        }
        if (!$connection->webhookEnabled) {
            return new WP_Error('jooosi_mail_webhook_disabled', 'Webhook not enabled for this connection.', ['status' => 404]);
        }
        try {
            $webhookAdapter = $this->webhookAdapterRegistry->resolve($connection);
        } catch (RuntimeException $exception) {
            return new WP_Error('jooosi_mail_webhook_adapter_missing', $exception->getMessage(), ['status' => 400]);
        }
        if ($webhookAdapter->describeVerification($connection) === 'unsupported') {
            return new WP_Error('jooosi_mail_webhook_verification_unsupported', 'Webhook verification is not supported for this connection.', ['status' => 403]);
        }
        if ($webhookAdapter->verify($request, $connection)) {
            return \true;
        }
        $this->eventPublisher->doAction('a!jooosi-mail/webhook:verification.failed', $connection, $request);
        return new WP_Error('jooosi_mail_invalid_webhook_signature', 'Invalid webhook signature.', ['status' => 401]);
    }
    /**
     * @since 0.1.0
     */
    private function resolveWebhookConnection(WP_REST_Request $request): ?Connection
    {
        $connectionId = (int) $request->get_param('connection_id');
        if ($connectionId <= 0) {
            return null;
        }
        return $this->connectionRepository->find($connectionId);
    }
}

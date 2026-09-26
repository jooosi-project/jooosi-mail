<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Application;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Webhook\Adapter\WebhookAdapterRegistry;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
/**
 * Authorizes incoming webhook requests using the selected provider adapter.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookAuthorizationService
{
    public function __construct(private readonly \JooosiMail\Webhook\Application\WebhookConnectionResolver $connectionResolver, private readonly WebhookAdapterRegistry $webhookAdapterRegistry, private readonly EventPublisherInterface $eventPublisher)
    {
    }
    /**
     * @since 1.0.9
     */
    public function authorize(WP_REST_Request $request): bool|WP_Error
    {
        $connection = $this->connectionResolver->fromRequest($request);
        if ($connection === null) {
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
}

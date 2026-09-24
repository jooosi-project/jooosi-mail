<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Delivery;

use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionDsnResolver;
use JooosiMail\Mail\Delivery\DeliveryService;
use JooosiMail\Mail\Delivery\EmailFactory;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Mail\Routing\ConnectionCircuitBreaker;
use JooosiMail\Mail\Routing\ConnectionRateLimiter;
use JooosiMail\Mail\Routing\ConnectionResolver;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;
use JooosiMail\Mail\Routing\DeliveryMode;
use JooosiMail\Mail\Routing\DeliveryPlan;
use JooosiMail\Mail\Routing\RoutingPolicyResolver;
use JooosiMail\Mail\Routing\RoutingStrategy;
use JooosiMail\Mail\Sender\SenderPolicyResolver;
use JooosiMail\Mail\Transport\TransportRegistry;
use JooosiMail\Mail\ValueObject\MailRequest;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Protects the DeliveryService façade and extracted collaborator boundary.
 *
 * @since 1.0.9
 */
final class DeliveryServiceBoundaryTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function testCandidateExecutionPreservesDeliveryFilterAndPayloadPersistence(): void
    {
        $connection = $this->createNullConnection();
        $deliveryPlan = new DeliveryPlan(
            mode: DeliveryMode::Sync,
            strategy: RoutingStrategy::Single,
            priority: 10,
            delaySeconds: 0,
            preferredConnectionId: $connection->id,
        );
        $mailLogId = $this->createMailLog($deliveryPlan, $this->createMailRequest('Original subject'));
        $deliveryFilter = static function (
            MailRequest $mailRequest,
            int $filteredMailLogId,
            Connection $filteredConnection,
            DeliveryPlan $filteredPlan,
        ): MailRequest {
            return $mailRequest->with([
                'subject' => 'Filtered subject',
            ]);
        };

        add_filter('f!jooosi-mail/mail:delivery.request', $deliveryFilter, 10, 4);

        try {
            $result = $this->container()->get(DeliveryService::class)->deliver($mailLogId);
        } finally {
            remove_filter('f!jooosi-mail/mail:delivery.request', $deliveryFilter, 10);
        }

        $mailLog = $this->mailLogRepository()->find($mailLogId);

        self::assertTrue($result->successful);
        self::assertIsArray($mailLog);
        self::assertSame('sent', $mailLog['status']);
        self::assertSame('Original subject', $mailLog['subject']);

        $payload = json_decode((string) $mailLog['payload_json'], true);

        self::assertIsArray($payload);
        self::assertSame('Filtered subject', $payload['subject']);
    }

    /**
     * @since 1.0.9
     */
    public function testSuccessfulRetryClearsPreviousMailLogError(): void
    {
        $connection = $this->createNullConnection();
        $deliveryPlan = new DeliveryPlan(
            mode: DeliveryMode::Sync,
            strategy: RoutingStrategy::Single,
            priority: 10,
            delaySeconds: 0,
            preferredConnectionId: $connection->id,
        );
        $mailLogId = $this->createMailLog($deliveryPlan);
        $this->mailLogRepository()->markFailed($mailLogId, 'No connection could deliver this message.');

        $result = $this->container()->get(DeliveryService::class)->deliver($mailLogId);
        $mailLog = $this->mailLogRepository()->find($mailLogId);

        self::assertTrue($result->successful);
        self::assertIsArray($mailLog);
        self::assertSame('sent', $mailLog['status']);
        self::assertNull($mailLog['last_error']);
    }

    /**
     * The original thirteen positional constructor arguments remain valid for
     * integrations that instantiate DeliveryService outside the container.
     *
     * @since 1.0.9
     */
    public function testLegacyPositionalConstructorStillBuildsDeliveryCollaborators(): void
    {
        $connection = $this->createNullConnection();
        $deliveryPlan = new DeliveryPlan(
            mode: DeliveryMode::Sync,
            strategy: RoutingStrategy::Single,
            priority: 10,
            delaySeconds: 0,
            preferredConnectionId: $connection->id,
        );
        $mailLogId = $this->createMailLog($deliveryPlan);
        $container = $this->container();
        $deliveryService = new DeliveryService(
            $container->get(MailLogRepository::class),
            $container->get(MailAttemptRepository::class),
            $container->get(ConnectionResolver::class),
            $container->get(ConnectionStatusReporter::class),
            $container->get(ConnectionRateLimiter::class),
            $container->get(ConnectionCircuitBreaker::class),
            $container->get(RoutingPolicyResolver::class),
            $container->get(ConnectionDsnResolver::class),
            $container->get(TransportRegistry::class),
            $container->get(EmailFactory::class),
            $container->get(SenderPolicyResolver::class),
            $container->get(EventPublisherInterface::class),
            $container->get(MailLogRetentionService::class),
        );

        $result = $deliveryService->deliver($mailLogId);

        self::assertTrue($result->successful);
        self::assertSame('sent', $this->mailLogRepository()->find($mailLogId)['status'] ?? null);
    }
}

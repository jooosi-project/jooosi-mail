<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Delivery;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionDsnResolver;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\Routing\DeliveryPlan;
use JooosiMail\Mail\Sender\SenderPolicyResolver;
use JooosiMail\Mail\Transport\TransportRegistry;
use JooosiMail\Mail\ValueObject\MailRequest;
use Symfony\Component\Mailer\SentMessage;

/**
 * Executes one selected connection candidate.
 *
 * @since 1.0.9
 */
#[Service]
final class DeliveryCandidateExecutor
{
    public function __construct(
        private readonly ConnectionDsnResolver $connectionDsnResolver,
        private readonly TransportRegistry $transportRegistry,
        private readonly EmailFactory $emailFactory,
        private readonly SenderPolicyResolver $senderPolicyResolver,
        private readonly MailLogRepository $mailLogRepository,
        private readonly EventPublisherInterface $eventPublisher,
    ) {
    }

    /**
     * @since 1.0.9
     */
    public function execute(
        MailRequest $mailRequest,
        int $mailLogId,
        Connection $connection,
        DeliveryPlan $deliveryPlan,
    ): ?SentMessage {
        $transport = $this->transportRegistry->create($this->connectionDsnResolver->resolve($connection));
        $deliveryMailRequest = $this->senderPolicyResolver->apply(
            $this->filterDeliveryMailRequest($mailRequest, $mailLogId, $connection, $deliveryPlan),
            $connection,
        );

        if ($deliveryMailRequest !== $mailRequest) {
            $this->mailLogRepository->updatePayload($mailLogId, $deliveryMailRequest);
        }

        return $transport->send(
            $this->emailFactory->create($deliveryMailRequest),
            $this->emailFactory->createEnvelope($deliveryMailRequest),
        );
    }

    /**
     * @since 1.0.9
     */
    private function filterDeliveryMailRequest(
        MailRequest $mailRequest,
        int $mailLogId,
        Connection $connection,
        DeliveryPlan $deliveryPlan,
    ): MailRequest {
        $filteredMailRequest = $this->eventPublisher->applyFilters(
            'f!jooosi-mail/mail:delivery.request',
            $mailRequest,
            $mailLogId,
            $connection,
            $deliveryPlan,
        );

        return $filteredMailRequest instanceof MailRequest ? $filteredMailRequest : $mailRequest;
    }
}

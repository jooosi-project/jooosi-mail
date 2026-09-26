<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Delivery;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Event\EventPublisherInterface;
use JooosiMail\Mail\Connection\ConnectionDsnResolver;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Mail\Logging\MailLogRepository;
use JooosiMail\Mail\Logging\MailLogRetentionService;
use JooosiMail\Mail\Routing\ConnectionCircuitBreaker;
use JooosiMail\Mail\Routing\ConnectionRateLimiter;
use JooosiMail\Mail\Routing\ConnectionResolver;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;
use JooosiMail\Mail\Routing\RoutingPolicyResolver;
use JooosiMail\Mail\Sender\SenderPolicyResolver;
use JooosiMail\Mail\Transport\TransportRegistry;
use JooosiMail\Mail\ValueObject\DeliveryResult;
use Throwable;
/**
 * Executes delivery attempts for a logged email.
 *
 * @since 0.1.0
 */
#[Service]
final class DeliveryService
{
    private readonly \JooosiMail\Mail\Delivery\DeliveryMailLogLoader $mailLogLoader;
    private readonly \JooosiMail\Mail\Delivery\DeliveryMailLogReconciler $mailLogReconciler;
    private readonly \JooosiMail\Mail\Delivery\DeliveryCandidateExecutor $candidateExecutor;
    private readonly \JooosiMail\Mail\Delivery\DeliveryAttemptRecorder $attemptRecorder;
    private readonly \JooosiMail\Mail\Delivery\DeliveryOutcomeHandler $outcomeHandler;
    public function __construct(private readonly MailLogRepository $mailLogRepository, private readonly MailAttemptRepository $mailAttemptRepository, private readonly ConnectionResolver $connectionResolver, private readonly ConnectionStatusReporter $connectionStatusReporter, private readonly ConnectionRateLimiter $connectionRateLimiter, private readonly ConnectionCircuitBreaker $connectionCircuitBreaker, private readonly RoutingPolicyResolver $routingPolicyResolver, private readonly ConnectionDsnResolver $connectionDsnResolver, private readonly TransportRegistry $transportRegistry, private readonly \JooosiMail\Mail\Delivery\EmailFactory $emailFactory, private readonly SenderPolicyResolver $senderPolicyResolver, private readonly EventPublisherInterface $eventPublisher, private readonly MailLogRetentionService $mailLogRetentionService, ?\JooosiMail\Mail\Delivery\DeliveryMailLogLoader $mailLogLoader = null, ?\JooosiMail\Mail\Delivery\DeliveryMailLogReconciler $mailLogReconciler = null, ?\JooosiMail\Mail\Delivery\DeliveryCandidateExecutor $candidateExecutor = null, ?\JooosiMail\Mail\Delivery\DeliveryAttemptRecorder $attemptRecorder = null, ?\JooosiMail\Mail\Delivery\DeliveryOutcomeHandler $outcomeHandler = null)
    {
        $this->mailLogLoader = $mailLogLoader ?? new \JooosiMail\Mail\Delivery\DeliveryMailLogLoader($this->mailLogRepository, $this->mailAttemptRepository);
        $this->mailLogReconciler = $mailLogReconciler ?? new \JooosiMail\Mail\Delivery\DeliveryMailLogReconciler($this->mailLogRepository, $this->mailLogRetentionService, $this->eventPublisher);
        $this->candidateExecutor = $candidateExecutor ?? new \JooosiMail\Mail\Delivery\DeliveryCandidateExecutor($this->connectionDsnResolver, $this->transportRegistry, $this->emailFactory, $this->senderPolicyResolver, $this->mailLogRepository, $this->eventPublisher);
        $this->attemptRecorder = $attemptRecorder ?? new \JooosiMail\Mail\Delivery\DeliveryAttemptRecorder($this->mailAttemptRepository, $this->mailLogRepository, $this->connectionCircuitBreaker, $this->eventPublisher);
        $this->outcomeHandler = $outcomeHandler ?? new \JooosiMail\Mail\Delivery\DeliveryOutcomeHandler($this->mailLogRepository, $this->connectionStatusReporter, $this->eventPublisher, $this->mailLogRetentionService);
    }
    /**
     * @since 0.1.0
     */
    public function deliver(int $mailLogId, bool $finalizeFailures = \true): DeliveryResult
    {
        $mailLog = $this->mailLogLoader->find($mailLogId);
        if (!is_array($mailLog)) {
            return new DeliveryResult(successful: \false, error: 'Mail log not found.');
        }
        if (($mailLog['status'] ?? null) === 'sent') {
            return new DeliveryResult(successful: \true);
        }
        $sentAttempt = $this->mailLogLoader->findLatestSentAttempt($mailLogId);
        if (is_array($sentAttempt)) {
            return $this->mailLogReconciler->reconcile($mailLogId, $sentAttempt);
        }
        $mailRequest = $this->mailLogLoader->createRequest($mailLog);
        $deliveryPlan = $this->routingPolicyResolver->resolve($mailRequest);
        $connections = $this->connectionResolver->resolve($deliveryPlan);
        if ($connections === []) {
            return $this->outcomeHandler->handleNoAvailableConnections($mailLogId, $finalizeFailures);
        }
        $this->mailLogRepository->markProcessing($mailLogId);
        $reservedAnyConnection = \false;
        $connectionErrors = [];
        foreach ($connections as $connection) {
            if ($connection->id === null) {
                continue;
            }
            if (!$this->connectionRateLimiter->reserve($connection)) {
                continue;
            }
            $reservedAnyConnection = \true;
            try {
                $sentMessage = $this->candidateExecutor->execute($mailRequest, $mailLogId, $connection, $deliveryPlan);
            } catch (Throwable $throwable) {
                $this->attemptRecorder->recordFailure($mailLogId, $connection->id, $connection, $throwable);
                $error = trim($throwable->getMessage());
                if ($error !== '') {
                    $connectionError = sprintf('%s: %s', $connection->name, $error);
                    if (!isset($connectionErrors[$connectionError]) && count($connectionErrors) < 3) {
                        $connectionErrors[$connectionError] = $connectionError;
                    }
                }
                continue;
            }
            $transportMessageId = $sentMessage->getMessageId();
            $debug = $sentMessage->getDebug();
            $normalizedDebug = is_string($debug) ? $debug : (wp_json_encode($debug) ?: null);
            $this->attemptRecorder->recordAccepted(mailLogId: $mailLogId, connectionId: $connection->id, transportMessageId: $transportMessageId, debug: $normalizedDebug);
            $this->attemptRecorder->recordSuccess($connection);
            $this->outcomeHandler->dispatchMailSentAction($mailLogId, $connection->id, $transportMessageId);
            $this->outcomeHandler->cleanupTerminalLog($mailLogId);
            return new DeliveryResult(successful: \true, connectionId: $connection->id, transportMessageId: $transportMessageId, debug: $normalizedDebug);
        }
        if (!$reservedAnyConnection) {
            return $this->outcomeHandler->handleNoAvailableConnections($mailLogId, $finalizeFailures);
        }
        $deliveryResult = $this->outcomeHandler->handleExhaustedCandidates($mailLogId, $finalizeFailures);
        if ($connectionErrors === []) {
            return $deliveryResult;
        }
        return new DeliveryResult(successful: \false, error: implode(' | ', array_values($connectionErrors)), temporaryFailure: $deliveryResult->temporaryFailure, retryAfterSeconds: $deliveryResult->retryAfterSeconds);
    }
}

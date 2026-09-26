<?php

declare (strict_types=1);
namespace JooosiMail\Admin\ReadModel;

use JooosiMail\Admin\Presentation\Log\MailAttemptPresenter;
use JooosiMail\Admin\Presentation\Log\QueueMessagePresenter;
use JooosiMail\Admin\Presentation\Log\WebhookEventPresenter;
use JooosiMail\Admin\ReadModel\Log\AdminLogSummaryReadModel;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Queue\Failure\FailedMessageRepository;
use JooosiMail\Queue\Query\QueueMessageQuery;
use JooosiMail\Webhook\Event\WebhookEventRepository;
/**
 * Reads the aggregate payload for the admin log overview.
 *
 * @since 1.0.9
 */
#[Service]
final class OverviewReadModel
{
    public function __construct(private readonly AdminLogSummaryReadModel $logSummaryReadModel, private readonly MailAttemptRepository $mailAttemptRepository, private readonly WebhookEventRepository $webhookEventRepository, private readonly FailedMessageRepository $failedMessageRepository, private readonly QueueMessageQuery $queueMessageQuery, private readonly MailAttemptPresenter $mailAttemptPresenter, private readonly WebhookEventPresenter $webhookEventPresenter, private readonly QueueMessagePresenter $queueMessagePresenter)
    {
    }
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function payload(): array
    {
        $queueSummary = $this->queueMessageQuery->getStatusSnapshot();
        return ['summary' => ['mail' => $this->logSummaryReadModel->mailSummary(), 'queue' => ['pendingReady' => (int) ($queueSummary['pending_ready'] ?? 0), 'pendingDeferred' => (int) ($queueSummary['pending_deferred'] ?? 0), 'processing' => (int) ($queueSummary['processing'] ?? 0), 'staleProcessing' => (int) ($queueSummary['stale_processing'] ?? 0), 'failed' => (int) ($queueSummary['failed'] ?? 0), 'completed' => (int) ($queueSummary['completed'] ?? 0)], 'webhookEvents' => $this->logSummaryReadModel->webhookEventCount(), 'failedMessages' => $this->failedMessageRepository->count()], 'attempts' => $this->mailAttemptPresenter->presentMany($this->mailAttemptRepository->listRecent(50)), 'events' => $this->webhookEventPresenter->presentMany($this->webhookEventRepository->listRecent(50), \false), 'failedMessages' => $this->queueMessagePresenter->presentMany($this->failedMessageRepository->list(25)), 'processingMessages' => $this->queueMessagePresenter->presentMany($this->queueMessageQuery->listProcessing(25))];
    }
}

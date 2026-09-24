<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller;

use JooosiMail\Admin\Presentation\DashboardConnectionPresenter;
use JooosiMail\Admin\Presentation\OperationalStatusPresenter;
use JooosiMail\Admin\Presentation\Log\MailAttemptPresenter;
use JooosiMail\Admin\Presentation\Log\QueueMessagePresenter;
use JooosiMail\Admin\Presentation\Log\WebhookEventPresenter;
use JooosiMail\Admin\ReadModel\DashboardReadModel;
use JooosiMail\Admin\ReadModel\Log\AdminLogSummaryReadModel;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;
use JooosiMail\Mail\Logging\MailAttemptRepository;
use JooosiMail\Queue\Query\QueueMessageQuery;
use JooosiMail\Webhook\Event\WebhookEventRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves operational data for the admin dashboard.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/dashboard')]
final class DashboardController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly DashboardReadModel $dashboardReadModel,
        private readonly AdminLogSummaryReadModel $logSummaryReadModel,
        private readonly OptionStore $optionStore,
        private readonly ConnectionStatusReporter $connectionStatusReporter,
        private readonly DashboardConnectionPresenter $dashboardConnectionPresenter,
        private readonly OperationalStatusPresenter $operationalStatusPresenter,
        private readonly QueueMessageQuery $queueMessageQuery,
        private readonly MailAttemptRepository $mailAttemptRepository,
        private readonly WebhookEventRepository $webhookEventRepository,
        private readonly MailAttemptPresenter $mailAttemptPresenter,
        private readonly WebhookEventPresenter $webhookEventPresenter,
        private readonly QueueMessagePresenter $queueMessagePresenter,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $connectionSummary = $this->connectionStatusReporter->summarizeActiveConnections();
        $queueSummary = $this->queueMessageQuery->getStatusSnapshot();
        $mailSummary = $this->logSummaryReadModel->mailSummary();
        $connectionStatuses = $this->connectionStatusReporter->getStatuses(true);

        return new WP_REST_Response([
            'summary' => [
                'deliveryMode' => (string) $this->optionStore->get('settings.delivery.mode', 'async'),
                'routingStrategy' => (string) $this->optionStore->get('settings.delivery.strategy', 'weighted_random'),
                'interceptEnabled' => (bool) $this->optionStore->get('settings.mail.intercept.enabled', true),
                'connectionsTotal' => count($connectionStatuses),
                'activeConnections' => (int) ($connectionSummary['active_connections'] ?? 0),
                'availableConnections' => (int) ($connectionSummary['available_connections'] ?? 0),
                'temporarilyUnavailableConnections' => (int) ($connectionSummary['temporarily_unavailable_connections'] ?? 0),
                'nextAvailableAt' => $this->operationalStatusPresenter->dateTime($connectionSummary['next_available_at'] ?? null),
                'queuePendingReady' => (int) ($queueSummary['pending_ready'] ?? 0),
                'queuePendingDeferred' => (int) ($queueSummary['pending_deferred'] ?? 0),
                'queueProcessing' => (int) ($queueSummary['processing'] ?? 0),
                'queueStaleProcessing' => (int) ($queueSummary['stale_processing'] ?? 0),
                'queueFailed' => (int) ($queueSummary['failed'] ?? 0),
                'queueCompleted' => (int) ($queueSummary['completed'] ?? 0),
                'mailPending' => (int) ($mailSummary['pending'] ?? 0),
                'mailQueued' => (int) ($mailSummary['queued'] ?? 0),
                'mailProcessing' => (int) ($mailSummary['processing'] ?? 0),
                'mailSent' => (int) ($mailSummary['sent'] ?? 0),
                'mailFailed' => (int) ($mailSummary['failed'] ?? 0),
                'mailTotal' => (int) ($mailSummary['total'] ?? 0),
                'webhookEvents' => $this->logSummaryReadModel->webhookEventCount(),
            ],
            'connections' => $this->dashboardConnectionPresenter->presentMany($connectionStatuses),
            'sendingStats' => $this->dashboardReadModel->sendingStats($request->get_param('fromDate'), $request->get_param('toDate')),
            'recentAttempts' => $this->mailAttemptPresenter->presentMany($this->mailAttemptRepository->listRecent(8)),
            'recentWebhooks' => $this->webhookEventPresenter->presentMany($this->webhookEventRepository->listRecent(8), false),
            'failedMessages' => $this->queueMessagePresenter->presentMany($this->queueMessageQuery->listFailed(5), false),
        ]);
    }
}

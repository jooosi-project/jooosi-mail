<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Controller\Log;

use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Admin\ReadModel\Log\LogQuery;
use JooosiMail\Admin\ReadModel\Log\WebhookLogReadModel;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use WP_REST_Request;
use WP_REST_Response;
/**
 * Serves webhook log data for the admin UI.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/logs/webhooks')]
final class WebhookController
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly WebhookLogReadModel $webhookLogReadModel, private readonly \JooosiMail\Admin\Controller\Log\LogQueryNormalizer $logQueryNormalizer)
    {
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $query = $this->logQueryNormalizer->normalize($request, ['id', 'eventType', 'dateTime', 'connection', 'mailLogId'], ['eventTypes', 'connectionIds']);
        $page = $this->webhookLogReadModel->search(LogQuery::fromArray($query));
        return new WP_REST_Response(['items' => $page->items(), 'pagination' => $page->pagination(), 'filters' => $page->filters()]);
    }
}

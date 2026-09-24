<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller\Log;

use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Admin\ReadModel\Log\LogQuery;
use JooosiMail\Admin\ReadModel\Log\QueueLogReadModel;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves queue log data for the admin UI.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/logs/queue')]
final class QueueController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly QueueLogReadModel $queueLogReadModel,
        private readonly LogQueryNormalizer $logQueryNormalizer,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $query = $this->logQueryNormalizer->normalize(
            $request,
            ['id', 'status', 'priority', 'attempts', 'dateTime'],
            ['statuses'],
        );
        $page = $this->queueLogReadModel->search(LogQuery::fromArray($query));

        return new WP_REST_Response([
            'items' => $page->items(),
            'pagination' => $page->pagination(),
            'filters' => $page->filters(),
        ]);
    }
}

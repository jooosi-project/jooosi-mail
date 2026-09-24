<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller\Log;

use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Admin\ReadModel\OverviewReadModel;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves admin log overview data.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/logs')]
final class OverviewController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly OverviewReadModel $overviewReadModel,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->overviewReadModel->payload());
    }
}

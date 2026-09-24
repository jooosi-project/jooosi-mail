<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller;

use JooosiMail\Admin\Application\ConnectionApplicationService;
use JooosiMail\Admin\Request\ConnectionRequestNormalizer;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionConfigurationException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles admin CRUD requests for connections.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/connections')]
final class ConnectionController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly ConnectionApplicationService $connectionApplicationService,
        private readonly ConnectionRequestNormalizer $connectionRequestNormalizer,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->connectionApplicationService->listPayload());
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $connection = $this->connectionApplicationService->create($this->connectionRequestNormalizer->normalize($request));
        } catch (ConnectionConfigurationException $exception) {
            return $this->invalidConnectionError($exception);
        }

        return new WP_REST_Response([
            'connection' => $this->connectionApplicationService->detailPayload($connection),
        ], 201);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        return new WP_REST_Response([
            'connection' => $this->connectionApplicationService->detailPayload($connection),
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)', methods: ['PUT', 'PATCH'], permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        try {
            $updatedConnection = $this->connectionApplicationService->update(
                (int) $connection->id,
                $this->connectionRequestNormalizer->normalize($request),
            );
        } catch (ConnectionConfigurationException $exception) {
            return $this->invalidConnectionError($exception);
        }

        return new WP_REST_Response([
            'connection' => $this->connectionApplicationService->detailPayload($updatedConnection),
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)', methods: 'DELETE', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $this->connectionApplicationService->delete((int) $connection->id);

        return new WP_REST_Response([
            'deleted' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)/default', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function makeDefault(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $defaultConnection = $this->connectionApplicationService->makeDefault((int) $connection->id);

        return new WP_REST_Response([
            'connection' => $this->connectionApplicationService->detailPayload($defaultConnection),
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)/enabled', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function setEnabled(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $enabled = $this->connectionRequestNormalizer->normalizeEnabled($request);

        if ($enabled instanceof WP_Error) {
            return $enabled;
        }

        try {
            $updatedConnection = $this->connectionApplicationService->setEnabled((int) $connection->id, $enabled);
        } catch (ConnectionConfigurationException $exception) {
            return $this->invalidConnectionError($exception);
        }

        return new WP_REST_Response([
            'connection' => $this->connectionApplicationService->detailPayload($updatedConnection),
        ]);
    }

    /**
     * @since 1.0.9
     */
    private function resolveConnection(WP_REST_Request $request): Connection|WP_Error
    {
        $connectionId = (int) $request->get_param('connection_id');

        if ($connectionId <= 0) {
            return new WP_Error('jooosi_mail_invalid_connection_id', 'A valid connection id is required.', ['status' => 400]);
        }

        $connection = $this->connectionApplicationService->find($connectionId);

        if (! $connection instanceof Connection) {
            return new WP_Error('jooosi_mail_connection_not_found', 'Connection not found.', ['status' => 404]);
        }

        return $connection;
    }

    /**
     * @since 1.0.9
     */
    private function invalidConnectionError(ConnectionConfigurationException $exception): WP_Error
    {
        return new WP_Error('jooosi_mail_invalid_connection', $exception->getMessage(), ['status' => 400]);
    }
}

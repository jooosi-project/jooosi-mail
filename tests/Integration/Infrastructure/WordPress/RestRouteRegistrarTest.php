<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Infrastructure\WordPress;

use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Discovery\Runtime\DiscoveryManifest;
use JooosiMail\Infrastructure\WordPress\RestRouteRegistrar;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Covers permission callback resolution for REST route attributes.
 *
 * @since 0.1.0
 */
final class RestRouteRegistrarTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testControllerPermissionCallbackUsesControllerServiceMethod(): void
    {
        $controller = new RoutePermissionTestController();
        $permissionService = new RoutePermissionTestService();

        $this->registerTestRoutes($controller, $permissionService);

        $response = rest_do_request(new WP_REST_Request('GET', '/jooosi-mail/v1/testing-permissions/controller'));

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $controller->controllerPermissionCalls);
    }

    /**
     * @since 0.1.0
     */
    public function testArrayPermissionCallbackResolvesServiceInstanceMethodFromContainer(): void
    {
        $controller = new RoutePermissionTestController();
        $permissionService = new RoutePermissionTestService();

        $this->registerTestRoutes($controller, $permissionService);

        $response = rest_do_request(new WP_REST_Request('GET', '/jooosi-mail/v1/testing-permissions/service-array'));

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $permissionService->servicePermissionCalls);
    }

    /**
     * @since 0.1.0
     */
    public function testStringClassPermissionCallbackResolvesServiceInstanceMethodFromContainer(): void
    {
        $controller = new RoutePermissionTestController();
        $permissionService = new RoutePermissionTestService();

        $this->registerTestRoutes($controller, $permissionService);

        $response = rest_do_request(new WP_REST_Request('GET', '/jooosi-mail/v1/testing-permissions/service-string'));

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $permissionService->servicePermissionCalls);
    }

    /**
     * @since 0.1.0
     */
    public function testArrayPermissionCallbackSupportsStaticMethods(): void
    {
        $controller = new RoutePermissionTestController();
        $permissionService = new RoutePermissionTestService();

        RoutePermissionStaticCallbacks::$staticPermissionCalls = 0;
        $this->registerTestRoutes($controller, $permissionService);

        $response = rest_do_request(new WP_REST_Request('GET', '/jooosi-mail/v1/testing-permissions/static-array'));

        self::assertSame(200, $response->get_status());
        self::assertSame(1, RoutePermissionStaticCallbacks::$staticPermissionCalls);
    }

    /**
     * @since 0.1.0
     */
    private function registerTestRoutes(RoutePermissionTestController $controller, RoutePermissionTestService $permissionService): void
    {
        global $wp_rest_server;

        $wp_rest_server = null;
        rest_get_server();

        $registrar = new RestRouteRegistrar(
            new RoutePermissionTestContainer([
                RoutePermissionTestController::class => $controller,
                RoutePermissionTestService::class => $permissionService,
            ]),
            new DiscoveryManifest([], [RoutePermissionTestController::class], [], [], [], []),
        );

        add_action('rest_api_init', [$registrar, 'registerRoutes']);
        do_action('rest_api_init');
        remove_action('rest_api_init', [$registrar, 'registerRoutes']);
    }
}

/**
 * Minimal container for registrar callback resolution tests.
 *
 * @since 0.1.0
 */
final class RoutePermissionTestContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly array $services,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function get(string $id): mixed
    {
        if (! array_key_exists($id, $this->services)) {
            throw new RuntimeException(sprintf('Service "%s" not found.', $id));
        }

        return $this->services[$id];
    }

    /**
     * @since 0.1.0
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

/**
 * Test controller that exercises supported permission callback forms.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'testing-permissions')]
final class RoutePermissionTestController
{
    /**
     * @since 0.1.0
     */
    public int $controllerPermissionCalls = 0;

    /**
     * @since 0.1.0
     */
    #[Route(path: '/controller', methods: 'GET', permissionCallback: 'allowController')]
    public function controller(): WP_REST_Response
    {
        return new WP_REST_Response(['route' => 'controller'], 200);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/service-array', methods: 'GET', permissionCallback: [RoutePermissionTestService::class, 'allow'])]
    public function serviceArray(): WP_REST_Response
    {
        return new WP_REST_Response(['route' => 'service-array'], 200);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/service-string', methods: 'GET', permissionCallback: RoutePermissionTestService::class . '::allow')]
    public function serviceString(): WP_REST_Response
    {
        return new WP_REST_Response(['route' => 'service-string'], 200);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/static-array', methods: 'GET', permissionCallback: [RoutePermissionStaticCallbacks::class, 'allow'])]
    public function staticArray(): WP_REST_Response
    {
        return new WP_REST_Response(['route' => 'static-array'], 200);
    }

    /**
     * @since 0.1.0
     */
    public function allowController(WP_REST_Request $request): bool
    {
        ++$this->controllerPermissionCalls;

        return $request->get_method() === 'GET';
    }
}

/**
 * Test permission service resolved through the container.
 *
 * @since 0.1.0
 */
final class RoutePermissionTestService
{
    /**
     * @since 0.1.0
     */
    public int $servicePermissionCalls = 0;

    /**
     * @since 0.1.0
     */
    public function allow(WP_REST_Request $request): bool
    {
        ++$this->servicePermissionCalls;

        return $request->get_method() === 'GET';
    }
}

/**
 * Static permission callbacks used to verify native callable passthrough.
 *
 * @since 0.1.0
 */
final class RoutePermissionStaticCallbacks
{
    /**
     * @since 0.1.0
     */
    public static int $staticPermissionCalls = 0;

    /**
     * @since 0.1.0
     */
    public static function allow(WP_REST_Request $request): bool
    {
        ++self::$staticPermissionCalls;

        return $request->get_method() === 'GET';
    }
}

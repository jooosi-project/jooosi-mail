<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Admin\Controller;

use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Verifies admin log routes remain registered after controller splitting.
 *
 * @since 0.1.0
 */
final class LogControllerRoutesTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testAdminLogRoutesRemainRegistered(): void
    {
        global $wp_rest_server;

        $wp_rest_server = null;
        $server = rest_get_server();

        do_action('rest_api_init');

        $routes = $server->get_routes();

        self::assertArrayHasKey('/jooosi-mail/v1/admin/logs', $routes);
        self::assertArrayHasKey('/jooosi-mail/v1/admin/logs/mail', $routes);
        self::assertArrayHasKey('/jooosi-mail/v1/admin/logs/mail/(?P<mail_log_id>\d+)', $routes);
        self::assertArrayHasKey('/jooosi-mail/v1/admin/logs/mail/(?P<mail_log_id>\d+)/resend', $routes);
        self::assertArrayHasKey('/jooosi-mail/v1/admin/logs/mail/test', $routes);
        self::assertArrayHasKey('/jooosi-mail/v1/admin/logs/queue', $routes);
        self::assertArrayHasKey('/jooosi-mail/v1/admin/logs/webhooks', $routes);
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Admin\Controller;

use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Verifies the public admin connection REST contracts.
 *
 * @since 1.0.9
 */
final class ConnectionControllerTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function set_up(): void
    {
        parent::set_up();
        wp_set_current_user($this->factory()->user->create(['role' => 'administrator']));

        global $wp_rest_server;

        $wp_rest_server = null;
        rest_get_server();
        do_action('rest_api_init');
    }

    /**
     * @since 1.0.9
     */
    public function testListAndDetailKeepConnectionProjectionAndProfileContracts(): void
    {
        $connection = $this->createNullConnection([
            'name' => 'REST Null Connection',
            'default' => true,
        ]);

        $list = $this->request('GET', '/jooosi-mail/v1/admin/connections');
        self::assertInstanceOf(WP_REST_Response::class, $list);
        self::assertSame(200, $list->get_status());
        $listData = $list->get_data();

        self::assertArrayHasKey('profiles', $listData);
        self::assertArrayHasKey('connections', $listData);
        self::assertNotEmpty($listData['profiles']);

        $listItem = $this->findConnection($listData['connections'], $connection->id);
        self::assertSame('REST Null Connection', $listItem['name']);
        self::assertSame('null', $listItem['profile']['key']);
        self::assertFalse($listItem['webhookSecretConfigured']);
        self::assertFalse($listItem['dsnOverrideConfigured']);
        self::assertArrayHasKey('healthScore', $listItem);
        self::assertArrayHasKey('rateLimitStatus', $listItem);
        self::assertArrayHasKey('circuitBreakerStatus', $listItem);

        $detail = $this->request('GET', sprintf('/jooosi-mail/v1/admin/connections/%d', $connection->id));
        self::assertInstanceOf(WP_REST_Response::class, $detail);
        self::assertSame(200, $detail->get_status());
        $detailData = $detail->get_data()['connection'];

        self::assertSame('REST Null Connection', $detailData['name']);
        self::assertSame('null', $detailData['profile']['key']);
        self::assertSame(['minute' => null, 'hour' => null, 'day' => null], $detailData['rateLimits']);
        self::assertSame(['threshold' => null, 'window' => null, 'cooldown' => null], $detailData['circuitBreaker']);
        self::assertSame([
            'email' => '',
            'name' => '',
            'forceEmail' => false,
            'forceName' => false,
            'returnPathMode' => 'inherit',
            'returnPathEmail' => '',
        ], $detailData['sender']);
    }

    /**
     * @since 1.0.9
     */
    public function testCreateAndEnableRequestsPreserveNormalizationAndSecretRedaction(): void
    {
        $create = $this->request('POST', '/jooosi-mail/v1/admin/connections', [
            'profile' => 'smtp',
            'name' => 'REST SMTP Connection',
            'configuration' => [
                'host' => 'smtp.example.com',
                'username' => 'smtp-user',
            ],
            'secretConfiguration' => [
                'password' => [
                    'action' => 'replace',
                    'value' => 'super-secret-password',
                ],
            ],
            'webhookSecretAction' => 'replace',
            'webhookSecret' => 'super-secret-webhook-token',
        ]);

        self::assertInstanceOf(WP_REST_Response::class, $create);
        self::assertSame(201, $create->get_status());
        $connection = $create->get_data()['connection'];
        $connectionId = (int) $connection['id'];

        self::assertSame('REST SMTP Connection', $connection['name']);
        self::assertTrue($connection['webhookSecretConfigured']);
        $passwordField = null;

        foreach ($connection['configurationFields'] as $field) {
            if (($field['name'] ?? '') === 'password') {
                $passwordField = $field;
                break;
            }
        }

        self::assertIsArray($passwordField);
        self::assertTrue($passwordField['secret']);
        self::assertTrue($passwordField['configured']);
        self::assertStringNotContainsString('super-secret-password', wp_json_encode($connection));
        self::assertStringNotContainsString('super-secret-webhook-token', wp_json_encode($connection));

        $disabled = $this->request('POST', sprintf('/jooosi-mail/v1/admin/connections/%d/enabled', $connectionId), [
            'enabled' => 'false',
        ]);
        self::assertInstanceOf(WP_REST_Response::class, $disabled);
        self::assertFalse($disabled->get_data()['connection']['enabled']);
    }

    /**
     * @since 1.0.9
     */
    public function testConnectionErrorsKeepCodesStatusesAndMessages(): void
    {
        $missingId = $this->request('GET', '/jooosi-mail/v1/admin/connections/999999');
        $this->assertError($missingId, 'jooosi_mail_connection_not_found', 404, 'Connection not found.');

        $invalidCreate = $this->request('POST', '/jooosi-mail/v1/admin/connections', [
            'name' => 'Missing profile',
        ]);
        $this->assertError($invalidCreate, 'jooosi_mail_invalid_connection', 400, 'A connection profile key is required.');

        $connection = $this->createNullConnection(['name' => 'Toggle Connection']);
        $missingEnabled = $this->request('POST', sprintf('/jooosi-mail/v1/admin/connections/%d/enabled', $connection->id), []);
        $this->assertError($missingEnabled, 'jooosi_mail_missing_enabled', 400, 'The enabled field is required.');

        $invalidEnabled = $this->request('POST', sprintf('/jooosi-mail/v1/admin/connections/%d/enabled', $connection->id), [
            'enabled' => 'sometimes',
        ]);
        $this->assertError($invalidEnabled, 'jooosi_mail_invalid_enabled', 400, 'The enabled field must be a boolean.');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return WP_Error|WP_REST_Response
     *
     * @since 1.0.9
     */
    private function request(string $method, string $path, array $body = []): WP_Error|WP_REST_Response
    {
        $request = new WP_REST_Request($method, $path);

        if ($method !== 'GET') {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) wp_json_encode($body));
        }

        return rest_do_request($request);
    }

    /**
     * @param list<array<string, mixed>> $connections
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    private function findConnection(array $connections, ?int $connectionId): array
    {
        foreach ($connections as $connection) {
            if ((int) ($connection['id'] ?? 0) === $connectionId) {
                return $connection;
            }
        }

        self::fail(sprintf('Connection %d was not returned.', $connectionId));
    }

    /**
     * @param WP_Error|WP_REST_Response $response
     *
     * @since 1.0.9
     */
    private function assertError(WP_Error|WP_REST_Response $response, string $code, int $status, string $message): void
    {
        if ($response instanceof WP_Error) {
            self::assertSame($code, $response->get_error_code());
            self::assertSame($status, $response->get_error_data()['status']);
            self::assertSame($message, $response->get_error_message());

            return;
        }

        self::assertSame($status, $response->get_status());
        self::assertSame($code, $response->get_data()['code']);
        self::assertSame($message, $response->get_data()['message']);
    }
}

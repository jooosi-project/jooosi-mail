<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Admin\Controller;

use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use WP_REST_Request;

/**
 * Covers the stable admin settings REST contract.
 *
 * @since 1.0.9
 */
final class SettingsControllerTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function testPatchPreservesEveryOmittedSetting(): void
    {
        $this->authenticateAdmin();
        $this->registerRoutes();
        $this->optionStore()->set('settings.mail.intercept.enabled', false);
        $this->optionStore()->set('settings.mail.sender.email', 'sender@example.com');
        $this->optionStore()->set('settings.mail.sender.name', 'Existing sender');
        $this->optionStore()->set('settings.mail.sender.force_email', true);
        $this->optionStore()->set('settings.mail.sender.force_name', true);
        $this->optionStore()->set('settings.logging.email.enabled', false);
        $this->optionStore()->set('settings.logging.email.retention_days', 45);
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'failover');
        $this->optionStore()->set('settings.routing.rate_limits.minute', 12);
        $this->optionStore()->set('settings.routing.rate_limits.hour', 34);
        $this->optionStore()->set('settings.routing.rate_limits.day', 56);
        $this->optionStore()->set('settings.routing.circuit_breaker.threshold', 7);
        $this->optionStore()->set('settings.routing.circuit_breaker.window_seconds', 321);
        $this->optionStore()->set('settings.routing.circuit_breaker.cooldown_seconds', 654);
        $this->optionStore()->set('settings.queue.retry.max_retries', 8);
        $this->optionStore()->set('settings.queue.retry.delay_seconds', 90);
        $this->optionStore()->set('settings.queue.retry.multiplier', 4);
        $this->optionStore()->set('settings.queue.retry.max_delay_seconds', 1200);

        $request = $this->jsonRequest('PATCH', [
            'settings' => [
                'delivery' => [
                    'mode' => 'sync',
                ],
            ],
        ]);
        $response = rest_do_request($request);
        $settings = $response->get_data()['settings'];

        self::assertSame(200, $response->get_status());
        self::assertSame('sync', $settings['delivery']['mode']);
        self::assertSame('failover', $settings['delivery']['strategy']);
        self::assertFalse($settings['mail']['intercept']['enabled']);
        self::assertSame('sender@example.com', $settings['mail']['sender']['email']);
        self::assertSame('Existing sender', $settings['mail']['sender']['name']);
        self::assertTrue($settings['mail']['sender']['forceEmail']);
        self::assertTrue($settings['mail']['sender']['forceName']);
        self::assertFalse($settings['logging']['email']['enabled']);
        self::assertSame(45, $settings['logging']['email']['retentionDays']);
        self::assertSame(['minute' => 12, 'hour' => 34, 'day' => 56], $settings['routing']['rateLimits']);
        self::assertSame([
            'threshold' => 7,
            'windowSeconds' => 321,
            'cooldownSeconds' => 654,
        ], $settings['routing']['circuitBreaker']);
        self::assertSame([
            'maxRetries' => 8,
            'delaySeconds' => 90,
            'multiplier' => 4,
            'maxDelaySeconds' => 1200,
        ], $settings['queue']['retry']);
    }

    /**
     * @since 1.0.9
     */
    public function testPatchCanUpdateOneNestedSenderField(): void
    {
        $this->authenticateAdmin();
        $this->registerRoutes();
        $this->optionStore()->set('settings.mail.sender.email', 'sender@example.com');
        $this->optionStore()->set('settings.mail.sender.force_email', true);

        $response = rest_do_request($this->jsonRequest('PATCH', [
            'mail' => [
                'sender' => [
                    'name' => 'Updated sender',
                ],
            ],
        ]));
        $sender = $response->get_data()['settings']['mail']['sender'];

        self::assertSame(200, $response->get_status());
        self::assertSame('sender@example.com', $sender['email']);
        self::assertSame('Updated sender', $sender['name']);
        self::assertTrue($sender['forceEmail']);
    }

    /**
     * @since 1.0.9
     */
    public function testValidationErrorsKeepTheirExistingRestContract(): void
    {
        $this->authenticateAdmin();
        $this->registerRoutes();

        $response = rest_do_request($this->jsonRequest('PATCH', [
            'delivery' => [
                'mode' => 'unsupported',
            ],
        ]));

        self::assertSame(400, $response->get_status());
        self::assertSame('jooosi_mail_invalid_delivery_mode', $response->get_data()['code']);
    }

    /**
     * @since 1.0.9
     */
    public function testMalformedPatchSectionIsRejectedWithoutResettingIt(): void
    {
        $this->authenticateAdmin();
        $this->registerRoutes();
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'failover');

        $response = rest_do_request($this->jsonRequest('PATCH', [
            'delivery' => 'invalid',
        ]));

        self::assertSame(400, $response->get_status());
        self::assertSame('jooosi_mail_invalid_settings', $response->get_data()['code']);
        self::assertSame('sync', $this->optionStore()->get('settings.delivery.mode'));
        self::assertSame('failover', $this->optionStore()->get('settings.delivery.strategy'));
    }

    /**
     * @since 1.0.9
     */
    public function testMalformedSettingsWrapperIsRejectedWithoutPersistingDefaults(): void
    {
        $this->authenticateAdmin();
        $this->registerRoutes();
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'failover');

        $response = rest_do_request($this->jsonRequest('PATCH', [
            'settings' => 'invalid',
        ]));

        self::assertSame(400, $response->get_status());
        self::assertSame('jooosi_mail_invalid_settings', $response->get_data()['code']);
        self::assertSame('sync', $this->optionStore()->get('settings.delivery.mode'));
        self::assertSame('failover', $this->optionStore()->get('settings.delivery.strategy'));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @since 1.0.9
     */
    private function jsonRequest(string $method, array $payload): WP_REST_Request
    {
        $request = new WP_REST_Request($method, '/jooosi-mail/v1/admin/settings');
        $request->set_header('content-type', 'application/json');
        $request->set_body((string) wp_json_encode($payload));

        return $request;
    }

    /**
     * @since 1.0.9
     */
    private function authenticateAdmin(): void
    {
        wp_set_current_user($this->factory()->user->create([
            'role' => 'administrator',
        ]));
    }

    /**
     * @since 1.0.9
     */
    private function registerRoutes(): void
    {
        global $wp_rest_server;

        $wp_rest_server = null;
        rest_get_server();
        do_action('rest_api_init');
    }
}

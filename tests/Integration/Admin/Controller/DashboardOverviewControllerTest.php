<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Admin\Controller;

use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Verifies the dashboard and log overview REST contracts.
 *
 * @since 1.0.9
 */
final class DashboardOverviewControllerTest extends JooosiMailIntegrationTestCase
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
    public function testDashboardPreservesReversedDateRangeAndConnectionProjection(): void
    {
        $connection = $this->createNullConnection(['name' => 'Dashboard Null Connection']);
        $mailLogs = $this->tableNameResolver()->resolve('mail_logs');

        $this->db()->insert($mailLogs, [
            'source' => 'dashboard-test',
            'subject' => 'Dashboard sent mail',
            'recipients_json' => wp_json_encode([['address' => 'sent@example.com']]),
            'payload_json' => wp_json_encode([]),
            'plan_json' => wp_json_encode([]),
            'status' => 'sent',
            'created_at' => '2026-04-08 10:00:00',
            'sent_at' => '2026-04-08 10:01:00',
            'updated_at' => '2026-04-08 10:01:00',
        ]);
        $this->db()->insert($mailLogs, [
            'source' => 'dashboard-test',
            'subject' => 'Dashboard failed mail',
            'recipients_json' => wp_json_encode([['address' => 'failed@example.com']]),
            'payload_json' => wp_json_encode([]),
            'plan_json' => wp_json_encode([]),
            'status' => 'failed',
            'created_at' => '2026-04-10 10:00:00',
            'updated_at' => '2026-04-10 10:00:00',
        ]);

        $response = $this->request('GET', '/jooosi-mail/v1/admin/dashboard', [
            'fromDate' => '2026-04-10',
            'toDate' => '2026-04-08',
        ]);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(200, $response->get_status());
        $data = $response->get_data();

        self::assertSame(['2026-04-08', '2026-04-09', '2026-04-10'], array_column($data['sendingStats'], 'date'));
        self::assertSame(['date' => '2026-04-08', 'total' => 1, 'sent' => 1, 'failed' => 0], $data['sendingStats'][0]);
        self::assertSame(['date' => '2026-04-09', 'total' => 0, 'sent' => 0, 'failed' => 0], $data['sendingStats'][1]);
        self::assertSame(['date' => '2026-04-10', 'total' => 1, 'sent' => 0, 'failed' => 1], $data['sendingStats'][2]);

        $connectionPayload = $this->findConnection($data['connections'], $connection->id);
        self::assertSame('Dashboard Null Connection', $connectionPayload['name']);
        self::assertFalse($connectionPayload['webhookSecretConfigured']);
        self::assertArrayHasKey('rateLimit', $connectionPayload);
        self::assertArrayHasKey('circuitBreaker', $connectionPayload);
        self::assertArrayHasKey('webhookUrl', $connectionPayload);
    }

    /**
     * @since 1.0.9
     */
    public function testDashboardInvalidDatesKeepTheNinetyDayDefaultRange(): void
    {
        $response = $this->request('GET', '/jooosi-mail/v1/admin/dashboard', [
            'fromDate' => 'not-a-date',
            'toDate' => '2026-04-08',
        ]);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(200, $response->get_status());
        $stats = $response->get_data()['sendingStats'];

        self::assertCount(90, $stats);
        self::assertSame('2026-01-09', $stats[0]['date']);
        self::assertSame('2026-04-08', $stats[89]['date']);
    }

    /**
     * @since 1.0.9
     */
    public function testOverviewPreservesSummaryProjectionAndRecentItemLimits(): void
    {
        $response = $this->request('GET', '/jooosi-mail/v1/admin/logs');

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(200, $response->get_status());
        $data = $response->get_data();

        self::assertSame(['summary', 'attempts', 'events', 'failedMessages', 'processingMessages'], array_keys($data));
        self::assertSame(['mail', 'queue', 'webhookEvents', 'failedMessages'], array_keys($data['summary']));
        self::assertSame(
            ['pendingReady', 'pendingDeferred', 'processing', 'staleProcessing', 'failed', 'completed'],
            array_keys($data['summary']['queue']),
        );
        self::assertLessThanOrEqual(50, count($data['attempts']));
        self::assertLessThanOrEqual(50, count($data['events']));
        self::assertLessThanOrEqual(25, count($data['failedMessages']));
        self::assertLessThanOrEqual(25, count($data['processingMessages']));
    }

    /**
     * @param array<string, mixed> $query
     * @return WP_REST_Response
     *
     * @since 1.0.9
     */
    private function request(string $method, string $path, array $query = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, $path);
        $request->set_query_params($query);
        $response = rest_do_request($request);

        self::assertInstanceOf(WP_REST_Response::class, $response);

        return $response;
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
}

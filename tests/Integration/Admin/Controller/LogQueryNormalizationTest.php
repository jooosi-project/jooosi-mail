<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Admin\Controller;

use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use WP_REST_Request;

/**
 * Verifies shared log query handling through all three REST endpoints.
 *
 * @since 1.0.9
 */
final class LogQueryNormalizationTest extends JooosiMailIntegrationTestCase
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
     * @dataProvider logEndpoints
     *
     * @since 1.0.9
     */
    public function testMalformedValuesAreIgnoredWithoutDiscardingValidFilters(string $endpoint, string $filter, string $matchingValue): void
    {
        $this->seedLogs();

        $data = $this->requestLogs($endpoint, [
            $filter => [[$matchingValue], ' ', null, ' ' . $matchingValue . ' '],
            'connectionIds' => [['unassigned']],
            'search' => ['invalid'],
            'page' => ['invalid'],
            'perPage' => ['invalid'],
            'sortBy' => ['invalid'],
            'sortDirection' => ['invalid'],
            'fromDate' => ['2026-04-08'],
            'toDate' => '2026-02-30',
        ]);

        self::assertCount(1, $data['items']);
        self::assertSame($matchingValue, $data['items'][0][$filter === 'eventTypes' ? 'eventType' : 'status']);
        self::assertSame(['page' => 1, 'perPage' => 25, 'total' => 1, 'totalPages' => 1], $data['pagination']);
    }

    /**
     * @dataProvider logEndpoints
     *
     * @since 1.0.9
     */
    public function testPaginationAndDateBoundariesRemainConsistent(string $endpoint): void
    {
        $this->seedLogs();

        $data = $this->requestLogs($endpoint, [
            'page' => 999,
            'perPage' => 1,
            'sortBy' => 'id',
            'sortDirection' => 'asc',
            'fromDate' => '2026-04-08',
            'toDate' => '2026-04-08',
        ]);

        self::assertCount(1, $data['items']);
        self::assertSame('2026-04-08 23:59:59', $data['items'][0]['createdAt']);
        self::assertSame(['page' => 2, 'perPage' => 1, 'total' => 2, 'totalPages' => 2], $data['pagination']);

        $empty = $this->requestLogs($endpoint, ['fromDate' => '2026-04-10', 'perPage' => 1000]);

        self::assertSame([], $empty['items']);
        self::assertSame(['page' => 1, 'perPage' => 100, 'total' => 0, 'totalPages' => 1], $empty['pagination']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     *
     * @since 1.0.9
     */
    public static function logEndpoints(): iterable
    {
        yield 'mail' => ['mail', 'statuses', 'failed'];
        yield 'queue' => ['queue', 'statuses', 'failed'];
        yield 'webhooks' => ['webhooks', 'eventTypes', 'bounced'];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    private function requestLogs(string $endpoint, array $query): array
    {
        $request = new WP_REST_Request('GET', '/jooosi-mail/v1/admin/logs/' . $endpoint);
        $request->set_query_params($query);
        $response = rest_do_request($request);

        self::assertSame(200, $response->get_status());

        return $response->get_data();
    }

    /**
     * @since 1.0.9
     */
    private function seedLogs(): void
    {
        foreach (['2026-04-08 00:00:00', '2026-04-08 23:59:59', '2026-04-09 00:00:00'] as $index => $date) {
            $this->db()->insert($this->tableNameResolver()->resolve('mail_logs'), [
                'source' => 'test',
                'subject' => 'Log query test',
                'recipients_json' => '[]',
                'payload_json' => '[]',
                'plan_json' => '[]',
                'status' => $index === 1 ? 'failed' : 'sent',
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            $this->db()->insert($this->tableNameResolver()->resolve('queue_messages'), [
                'body' => '{}',
                'headers_json' => '[]',
                'status' => $index === 1 ? 'failed' : 'pending',
                'priority' => 10,
                'available_at' => $date,
                'attempt_count' => 0,
                'max_attempts' => 3,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            $this->db()->insert($this->tableNameResolver()->resolve('webhook_events'), [
                'event_type' => $index === 1 ? 'bounced' : 'delivered',
                'payload_json' => '[]',
                'created_at' => $date,
            ]);
        }
    }
}

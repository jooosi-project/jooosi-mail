<?php

declare (strict_types=1);
namespace JooosiMail\Webhook\Adapter;

use JooosiMail\Mail\Connection\Connection;
use WP_REST_Request;
/**
 * Normalizes provider webhook requests.
 *
 * @since 0.1.0
 */
interface WebhookAdapterInterface
{
    public function getPriority(): int;
    public function supports(Connection $connection): bool;
    public function verify(WP_REST_Request $request, Connection $connection): bool;
    public function describeVerification(Connection $connection): string;
    /** @return list<array<string, mixed>> */
    public function parse(WP_REST_Request $request, Connection $connection): array;
}

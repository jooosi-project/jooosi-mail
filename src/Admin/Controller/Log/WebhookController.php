<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Controller\Log;

use JooosiMailDeps\Doctrine\DBAL\ArrayParameterType;
use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMailDeps\Doctrine\DBAL\Query\QueryBuilder;
use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use WP_REST_Request;
use WP_REST_Response;
use function JooosiMailDeps\Symfony\Component\String\u;
/**
 * Serves webhook log data for the admin UI.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/logs/webhooks')]
final class WebhookController
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver)
    {
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $query = $this->normalizeWebhookLogQuery($request);
        $total = $this->countWebhookLogs($query);
        $perPage = $query['perPage'];
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($query['page'], $totalPages);
        $query['page'] = $page;
        return new WP_REST_Response(['items' => $this->normalizeWebhookLogRows($this->queryWebhookLogs($query)), 'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages], 'filters' => ['eventTypes' => $this->getWebhookEventTypeOptions($query), 'connections' => $this->getWebhookConnectionOptions($query)]]);
    }
    /**
     * @param array<string, mixed> $query
     *
     * @since 0.1.0
     */
    private function countWebhookLogs(array $query): int
    {
        $queryBuilder = $this->createWebhookLogQueryBuilder()->select('COUNT(*)');
        $this->applyWebhookLogFilters($queryBuilder, $query);
        return (int) $queryBuilder->executeQuery()->fetchOne();
    }
    /**
     * @param array<string, mixed> $query
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function queryWebhookLogs(array $query): array
    {
        $queryBuilder = $this->createWebhookLogQueryBuilder()->select('e.id', 'e.connection_id', 'e.mail_log_id', 'e.event_type', 'e.transport_message_id', 'e.provider_event_id', 'e.payload_json', 'e.occurred_at', 'e.created_at', 'c.name AS connection_name')->setFirstResult(($query['page'] - 1) * $query['perPage'])->setMaxResults($query['perPage']);
        $this->applyWebhookLogFilters($queryBuilder, $query);
        $this->applyWebhookLogSorting($queryBuilder, $query['sortBy'], $query['sortDirection']);
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }
    /**
     * @param array<string, mixed> $query
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 0.1.0
     */
    private function getWebhookEventTypeOptions(array $query): array
    {
        $filterQuery = $query;
        $filterQuery['eventTypes'] = [];
        $queryBuilder = $this->createWebhookLogQueryBuilder()->select('e.event_type AS event_type', 'COUNT(*) AS total')->groupBy('e.event_type')->orderBy('e.event_type', 'ASC');
        $this->applyWebhookLogFilters($queryBuilder, $filterQuery);
        return array_map(static fn(array $row): array => ['label' => u((string) ($row['event_type'] ?? ''))->replace('_', ' ')->replace('-', ' ')->title(\true)->toString(), 'value' => (string) ($row['event_type'] ?? ''), 'count' => (int) ($row['total'] ?? 0)], $queryBuilder->executeQuery()->fetchAllAssociative());
    }
    /**
     * @param array<string, mixed> $query
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 0.1.0
     */
    private function getWebhookConnectionOptions(array $query): array
    {
        $filterQuery = $query;
        $filterQuery['connectionIds'] = [];
        $queryBuilder = $this->createWebhookLogQueryBuilder()->select('e.connection_id AS connection_id', 'c.name AS connection_name', 'COUNT(*) AS total')->groupBy('e.connection_id', 'c.name')->orderBy('c.name', 'ASC');
        $this->applyWebhookLogFilters($queryBuilder, $filterQuery);
        return array_map(static fn(array $row): array => ['label' => isset($row['connection_name']) && $row['connection_name'] !== '' ? (string) $row['connection_name'] : 'Unassigned', 'value' => isset($row['connection_id']) && is_numeric($row['connection_id']) ? (string) (int) $row['connection_id'] : 'unassigned', 'count' => (int) ($row['total'] ?? 0)], $queryBuilder->executeQuery()->fetchAllAssociative());
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function normalizeWebhookLogQuery(WP_REST_Request $request): array
    {
        $perPage = (int) $request->get_param('perPage');
        $sortBy = (string) $request->get_param('sortBy');
        $sortDirection = strtoupper((string) $request->get_param('sortDirection'));
        $eventTypes = $request->get_param('eventTypes');
        $connectionIds = $request->get_param('connectionIds');
        if (!in_array($sortBy, ['id', 'eventType', 'dateTime', 'connection', 'mailLogId'], \true)) {
            $sortBy = 'dateTime';
        }
        return ['search' => trim((string) $request->get_param('search')), 'eventTypes' => is_array($eventTypes) ? array_values(array_filter(array_map(static fn(mixed $eventType): string => trim((string) $eventType), $eventTypes), static fn(string $eventType): bool => $eventType !== '')) : [], 'connectionIds' => is_array($connectionIds) ? array_values(array_filter(array_map(static fn(mixed $connectionId): string => trim((string) $connectionId), $connectionIds), static fn(string $connectionId): bool => $connectionId !== '')) : [], 'fromDate' => self::normalizeLogDate((string) $request->get_param('fromDate')), 'toDate' => self::normalizeLogDate((string) $request->get_param('toDate')), 'page' => max(1, (int) $request->get_param('page')), 'perPage' => min(100, max(1, $perPage > 0 ? $perPage : 25)), 'sortBy' => $sortBy, 'sortDirection' => $sortDirection === 'ASC' ? 'ASC' : 'DESC'];
    }
    /**
     * @since 0.1.0
     */
    private static function normalizeLogDate(string $value): ?string
    {
        $trimmedValue = trim($value);
        if ($trimmedValue === '' || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmedValue, $matches) !== 1) {
            return null;
        }
        if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }
        return $trimmedValue;
    }
    /**
     * @since 0.1.0
     */
    private function createWebhookLogQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->from($this->tableNameResolver->resolve('webhook_events'), 'e')->leftJoin('e', $this->tableNameResolver->resolve('connections'), 'c', 'c.id = e.connection_id');
    }
    /**
     * @param array<string, mixed> $query
     *
     * @since 0.1.0
     */
    private function applyWebhookLogFilters(QueryBuilder $queryBuilder, array $query): void
    {
        if ($query['search'] !== '') {
            $queryBuilder->andWhere('(LOWER(e.event_type) LIKE :search OR LOWER(COALESCE(e.transport_message_id, \'\')) LIKE :search OR LOWER(COALESCE(e.provider_event_id, \'\')) LIKE :search OR LOWER(e.payload_json) LIKE :search OR LOWER(COALESCE(c.name, \'\')) LIKE :search)')->setParameter('search', '%' . mb_strtolower((string) $query['search']) . '%');
        }
        if ($query['eventTypes'] !== []) {
            $queryBuilder->andWhere('e.event_type IN (:event_types)')->setParameter('event_types', $query['eventTypes'], ArrayParameterType::STRING);
        }
        $connectionIds = [];
        $includeUnassigned = \false;
        foreach ($query['connectionIds'] as $connectionId) {
            if ($connectionId === 'unassigned') {
                $includeUnassigned = \true;
                continue;
            }
            if (ctype_digit($connectionId)) {
                $connectionIds[] = (int) $connectionId;
            }
        }
        if ($connectionIds !== [] || $includeUnassigned) {
            $conditions = [];
            if ($connectionIds !== []) {
                $conditions[] = 'e.connection_id IN (:webhook_connection_ids)';
                $queryBuilder->setParameter('webhook_connection_ids', array_values(array_unique($connectionIds)), ArrayParameterType::INTEGER);
            }
            if ($includeUnassigned) {
                $conditions[] = 'e.connection_id IS NULL';
            }
            $queryBuilder->andWhere('(' . implode(' OR ', $conditions) . ')');
        }
        $dateExpression = $this->getWebhookLogDateTimeExpression();
        if ($query['fromDate'] !== null) {
            $queryBuilder->andWhere(sprintf('%s >= :webhook_from_date', $dateExpression))->setParameter('webhook_from_date', $query['fromDate'] . ' 00:00:00');
        }
        if ($query['toDate'] !== null) {
            $queryBuilder->andWhere(sprintf('%s <= :webhook_to_date', $dateExpression))->setParameter('webhook_to_date', $query['toDate'] . ' 23:59:59');
        }
    }
    /**
     * @since 0.1.0
     */
    private function applyWebhookLogSorting(QueryBuilder $queryBuilder, string $sortBy, string $sortDirection): void
    {
        $sortColumn = match ($sortBy) {
            'id' => 'e.id',
            'eventType' => 'e.event_type',
            'connection' => 'COALESCE(c.name, \'\')',
            'mailLogId' => 'COALESCE(e.mail_log_id, 0)',
            default => $this->getWebhookLogDateTimeExpression(),
        };
        $queryBuilder->orderBy($sortColumn, $sortDirection)->addOrderBy('e.id', 'DESC');
    }
    /**
     * @since 0.1.0
     */
    private function getWebhookLogDateTimeExpression(): string
    {
        return 'COALESCE(e.occurred_at, e.created_at)';
    }
    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeWebhookLogRows(array $events): array
    {
        return array_map(fn(array $event): array => ['id' => (int) ($event['id'] ?? 0), 'connectionId' => isset($event['connection_id']) ? (int) $event['connection_id'] : null, 'connectionName' => isset($event['connection_name']) ? (string) $event['connection_name'] : null, 'mailLogId' => isset($event['mail_log_id']) ? (int) $event['mail_log_id'] : null, 'eventType' => (string) ($event['event_type'] ?? ''), 'transportMessageId' => isset($event['transport_message_id']) ? (string) $event['transport_message_id'] : null, 'providerEventId' => isset($event['provider_event_id']) ? (string) $event['provider_event_id'] : null, 'payloadJson' => $this->normalizeJsonString($event['payload_json'] ?? null), 'occurredAt' => isset($event['occurred_at']) ? (string) $event['occurred_at'] : null, 'createdAt' => isset($event['created_at']) ? (string) $event['created_at'] : null], $events);
    }
    /**
     * @since 0.1.0
     */
    private function normalizeJsonString(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $decoded = json_decode($value, \true);
        if (!is_array($decoded)) {
            return $value;
        }
        return wp_json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
    }
}

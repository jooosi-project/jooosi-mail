<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller\Log;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Query\QueryBuilder;
use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Queue\Message\SendEmailMessage;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

use function Symfony\Component\String\u;

/**
 * Serves queue log data for the admin UI.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/logs/queue')]
final class QueueController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $query = $this->normalizeQueueLogQuery($request);
        $total = $this->countQueueLogs($query);
        $perPage = $query['perPage'];
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($query['page'], $totalPages);
        $query['page'] = $page;

        return new WP_REST_Response([
            'items' => $this->normalizeQueueMessages($this->queryQueueLogs($query)),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
            'filters' => [
                'statuses' => $this->getQueueLogStatusOptions($query),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @since 0.1.0
     */
    private function countQueueLogs(array $query): int
    {
        $queryBuilder = $this->createQueueLogQueryBuilder()->select('COUNT(*)');

        $this->applyQueueLogFilters($queryBuilder, $query);

        return (int) $queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * @param array<string, mixed> $query
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function queryQueueLogs(array $query): array
    {
        $queryBuilder = $this->createQueueLogQueryBuilder()
            ->select(
                'q.id',
                'q.status',
                'q.priority',
                'q.attempt_count',
                'q.max_attempts',
                'q.last_error',
                'q.available_at',
                'q.claimed_at',
                'q.processed_at',
                'q.body',
                'q.created_at',
                'q.updated_at',
            )
            ->setFirstResult(($query['page'] - 1) * $query['perPage'])
            ->setMaxResults($query['perPage']);

        $this->applyQueueLogFilters($queryBuilder, $query);
        $this->applyQueueLogSorting($queryBuilder, $query['sortBy'], $query['sortDirection']);

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param array<string, mixed> $query
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 0.1.0
     */
    private function getQueueLogStatusOptions(array $query): array
    {
        $filterQuery = $query;
        $filterQuery['statuses'] = [];

        $queryBuilder = $this->createQueueLogQueryBuilder()
            ->select('q.status AS status', 'COUNT(*) AS total')
            ->groupBy('q.status')
            ->orderBy('q.status', 'ASC');

        $this->applyQueueLogFilters($queryBuilder, $filterQuery);

        return array_map(static fn (array $row): array => [
            'label' => u((string) ($row['status'] ?? ''))->replace('_', ' ')->replace('-', ' ')->title(true)->toString(),
            'value' => (string) ($row['status'] ?? ''),
            'count' => (int) ($row['total'] ?? 0),
        ], $queryBuilder->executeQuery()->fetchAllAssociative());
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function normalizeQueueLogQuery(WP_REST_Request $request): array
    {
        $perPage = (int) $request->get_param('perPage');
        $sortBy = (string) $request->get_param('sortBy');
        $sortDirection = strtoupper((string) $request->get_param('sortDirection'));
        $statuses = $request->get_param('statuses');

        if (! in_array($sortBy, ['id', 'status', 'priority', 'attempts', 'dateTime'], true)) {
            $sortBy = 'dateTime';
        }

        return [
            'search' => trim((string) $request->get_param('search')),
            'statuses' => is_array($statuses)
                ? array_values(array_filter(array_map(static fn (mixed $status): string => trim((string) $status), $statuses), static fn (string $status): bool => $status !== ''))
                : [],
            'fromDate' => self::normalizeLogDate((string) $request->get_param('fromDate')),
            'toDate' => self::normalizeLogDate((string) $request->get_param('toDate')),
            'page' => max(1, (int) $request->get_param('page')),
            'perPage' => min(100, max(1, $perPage > 0 ? $perPage : 25)),
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection === 'ASC' ? 'ASC' : 'DESC',
        ];
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

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return $trimmedValue;
    }

    /**
     * @since 0.1.0
     */
    private function createQueueLogQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from($this->tableNameResolver->resolve('queue_messages'), 'q');
    }

    /**
     * @param array<string, mixed> $query
     *
     * @since 0.1.0
     */
    private function applyQueueLogFilters(QueryBuilder $queryBuilder, array $query): void
    {
        if ($query['search'] !== '') {
            $conditions = [
                'LOWER(q.status) LIKE :queue_search',
                'LOWER(COALESCE(q.last_error, \'\')) LIKE :queue_search',
            ];

            $queryBuilder->setParameter('queue_search', '%' . mb_strtolower((string) $query['search']) . '%');

            if (ctype_digit((string) $query['search'])) {
                $conditions[] = 'q.id = :queue_search_id';
                $queryBuilder->setParameter('queue_search_id', (int) $query['search']);
            }

            $queryBuilder->andWhere('(' . implode(' OR ', $conditions) . ')');
        }

        if ($query['statuses'] !== []) {
            $queryBuilder
                ->andWhere('q.status IN (:queue_statuses)')
                ->setParameter('queue_statuses', $query['statuses'], ArrayParameterType::STRING);
        }

        $dateExpression = $this->getQueueLogDateTimeExpression();

        if ($query['fromDate'] !== null) {
            $queryBuilder
                ->andWhere(sprintf('%s >= :queue_from_date', $dateExpression))
                ->setParameter('queue_from_date', $query['fromDate'] . ' 00:00:00');
        }

        if ($query['toDate'] !== null) {
            $queryBuilder
                ->andWhere(sprintf('%s <= :queue_to_date', $dateExpression))
                ->setParameter('queue_to_date', $query['toDate'] . ' 23:59:59');
        }
    }

    /**
     * @since 0.1.0
     */
    private function applyQueueLogSorting(QueryBuilder $queryBuilder, string $sortBy, string $sortDirection): void
    {
        $sortColumn = match ($sortBy) {
            'id' => 'q.id',
            'status' => 'q.status',
            'priority' => 'q.priority',
            'attempts' => 'q.attempt_count',
            default => $this->getQueueLogDateTimeExpression(),
        };

        $queryBuilder
            ->orderBy($sortColumn, $sortDirection)
            ->addOrderBy('q.id', 'DESC');
    }

    /**
     * @since 0.1.0
     */
    private function getQueueLogDateTimeExpression(): string
    {
        return 'COALESCE(q.updated_at, q.processed_at, q.claimed_at, q.available_at, q.created_at)';
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeQueueMessages(array $messages): array
    {
        return array_map(fn (array $message): array => [
            'id' => (int) ($message['id'] ?? 0),
            'mailLogId' => $this->extractQueueMessageMailLogId($message['body'] ?? null),
            'status' => (string) ($message['status'] ?? ''),
            'priority' => (int) ($message['priority'] ?? 0),
            'attemptCount' => (int) ($message['attempt_count'] ?? 0),
            'maxAttempts' => (int) ($message['max_attempts'] ?? 0),
            'lastError' => isset($message['last_error']) ? (string) $message['last_error'] : null,
            'availableAt' => isset($message['available_at']) ? (string) $message['available_at'] : null,
            'claimedAt' => isset($message['claimed_at']) ? (string) $message['claimed_at'] : null,
            'processedAt' => isset($message['processed_at']) ? (string) $message['processed_at'] : null,
            'createdAt' => isset($message['created_at']) ? (string) $message['created_at'] : null,
            'updatedAt' => isset($message['updated_at']) ? (string) $message['updated_at'] : null,
        ], $messages);
    }

    /**
     * @since 0.1.0
     */
    private function extractQueueMessageMailLogId(mixed $body): ?int
    {
        if (! is_string($body) || trim($body) === '') {
            return null;
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $body,
                'headers' => [],
            ]);
        } catch (Throwable) {
            return null;
        }

        $message = $envelope->getMessage();

        return $message instanceof SendEmailMessage ? $message->mailLogId : null;
    }
}

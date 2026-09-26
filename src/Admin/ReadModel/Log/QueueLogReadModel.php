<?php

declare (strict_types=1);
namespace JooosiMail\Admin\ReadModel\Log;

use JooosiMailDeps\Doctrine\DBAL\ArrayParameterType;
use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMailDeps\Doctrine\DBAL\Query\QueryBuilder;
use JooosiMail\Admin\Presentation\Log\QueueAttemptPresenter;
use JooosiMail\Admin\Presentation\Log\QueueMessagePresenter;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use JooosiMail\Queue\Logging\QueueAttemptRepository;
/**
 * Reads and projects queue log data for the admin API.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueLogReadModel
{
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver, private readonly QueueAttemptRepository $queueAttemptRepository, private readonly QueueAttemptPresenter $queueAttemptPresenter, private readonly QueueMessagePresenter $presenter, private readonly \JooosiMail\Admin\ReadModel\Log\LogFilterPresenter $filterPresenter)
    {
    }
    /**
     * @since 1.0.9
     */
    public function search(\JooosiMail\Admin\ReadModel\Log\LogQuery $query): \JooosiMail\Admin\ReadModel\Log\LogPage
    {
        $total = $this->count($query);
        $totalPages = max(1, (int) ceil($total / $query->perPage));
        $effectiveQuery = $query->withPage(min($query->page, $totalPages));
        $rows = $this->fetchRows($effectiveQuery);
        $attemptHistoryByMessageId = $this->queueAttemptPresenter->presentGroupedByQueueMessage($this->queueAttemptRepository->listForQueueMessages(array_map(static fn(array $row): int => (int) $row['id'], $rows)));
        $items = $this->presenter->presentMany($rows);
        foreach ($items as $index => $item) {
            $items[$index]['attemptHistory'] = $attemptHistoryByMessageId[(int) $item['id']] ?? [];
        }
        return \JooosiMail\Admin\ReadModel\Log\LogPage::fromQuery(items: $items, total: $total, query: $effectiveQuery, filters: ['statuses' => $this->statusOptions($query)]);
    }
    /**
     * @since 1.0.9
     */
    private function count(\JooosiMail\Admin\ReadModel\Log\LogQuery $query): int
    {
        $queryBuilder = $this->createQueryBuilder()->select('COUNT(*)');
        $this->applyFilters($queryBuilder, $query);
        return (int) $queryBuilder->executeQuery()->fetchOne();
    }
    /**
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    private function fetchRows(\JooosiMail\Admin\ReadModel\Log\LogQuery $query): array
    {
        $queryBuilder = $this->createQueryBuilder()->select('q.id', 'q.status', 'q.priority', 'q.attempt_count', 'q.max_attempts', 'q.last_error', 'q.available_at', 'q.claimed_at', 'q.claimed_worker_id', 'q.processed_at', 'q.body', 'q.created_at', 'q.updated_at')->setFirstResult($query->offset())->setMaxResults($query->perPage);
        $this->applyFilters($queryBuilder, $query);
        $this->applySorting($queryBuilder, $query->sortBy, $query->sortDirection);
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }
    /**
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 1.0.9
     */
    private function statusOptions(\JooosiMail\Admin\ReadModel\Log\LogQuery $query): array
    {
        $queryBuilder = $this->createQueryBuilder()->select('q.status AS status', 'COUNT(*) AS total')->groupBy('q.status')->orderBy('q.status', 'ASC');
        $this->applyFilters($queryBuilder, $query->withoutFilter('statuses'));
        return $this->filterPresenter->statuses($queryBuilder->executeQuery()->fetchAllAssociative());
    }
    /**
     * @since 1.0.9
     */
    private function createQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->from($this->tableNameResolver->resolve('queue_messages'), 'q');
    }
    /**
     * @since 1.0.9
     */
    private function applyFilters(QueryBuilder $queryBuilder, \JooosiMail\Admin\ReadModel\Log\LogQuery $query): void
    {
        if ($query->search !== '') {
            $conditions = ['LOWER(q.status) LIKE :queue_search', 'LOWER(COALESCE(q.last_error, \'\')) LIKE :queue_search'];
            $queryBuilder->setParameter('queue_search', '%' . mb_strtolower($query->search) . '%');
            if (ctype_digit($query->search)) {
                $conditions[] = 'q.id = :queue_search_id';
                $queryBuilder->setParameter('queue_search_id', (int) $query->search);
            }
            $queryBuilder->andWhere('(' . implode(' OR ', $conditions) . ')');
        }
        $statuses = $query->filter('statuses');
        if ($statuses !== []) {
            $queryBuilder->andWhere('q.status IN (:queue_statuses)')->setParameter('queue_statuses', $statuses, ArrayParameterType::STRING);
        }
        $dateExpression = $this->dateTimeExpression();
        if ($query->fromDate !== null) {
            $queryBuilder->andWhere(sprintf('%s >= :queue_from_date', $dateExpression))->setParameter('queue_from_date', $query->fromDate . ' 00:00:00');
        }
        if ($query->toDate !== null) {
            $queryBuilder->andWhere(sprintf('%s <= :queue_to_date', $dateExpression))->setParameter('queue_to_date', $query->toDate . ' 23:59:59');
        }
    }
    /**
     * @since 1.0.9
     */
    private function applySorting(QueryBuilder $queryBuilder, string $sortBy, string $sortDirection): void
    {
        $sortColumn = match ($sortBy) {
            'id' => 'q.id',
            'status' => 'q.status',
            'priority' => 'q.priority',
            'attempts' => 'q.attempt_count',
            default => $this->dateTimeExpression(),
        };
        $queryBuilder->orderBy($sortColumn, $sortDirection)->addOrderBy('q.id', 'DESC');
    }
    /**
     * @since 1.0.9
     */
    private function dateTimeExpression(): string
    {
        return 'COALESCE(q.updated_at, q.processed_at, q.claimed_at, q.available_at, q.created_at)';
    }
}

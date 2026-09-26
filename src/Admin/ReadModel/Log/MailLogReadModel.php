<?php

declare (strict_types=1);
namespace JooosiMail\Admin\ReadModel\Log;

use JooosiMailDeps\Doctrine\DBAL\ArrayParameterType;
use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMailDeps\Doctrine\DBAL\Query\QueryBuilder;
use JooosiMail\Admin\Presentation\Log\MailLogPresenter;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
/**
 * Reads and projects mail log data for the admin API.
 *
 * @since 1.0.9
 */
#[Service]
final class MailLogReadModel
{
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver, private readonly MailLogPresenter $presenter, private readonly \JooosiMail\Admin\ReadModel\Log\LogFilterPresenter $filterPresenter)
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
        return \JooosiMail\Admin\ReadModel\Log\LogPage::fromQuery(items: $this->presenter->presentMany($this->fetchRows($effectiveQuery)), total: $total, query: $effectiveQuery, filters: ['statuses' => $this->statusOptions($query), 'connections' => $this->connectionOptions($query)]);
    }
    /**
     * @since 1.0.9
     */
    public function find(int $mailLogId): ?array
    {
        $queryBuilder = $this->createQueryBuilder()->select('l.id', 'l.source', 'l.subject', 'l.status', 'l.final_connection_id', 'l.transport_message_id', 'l.last_error', 'l.recipients_json', 'l.payload_json', 'l.created_at', 'l.queued_at', 'l.sent_at', 'l.updated_at', 'c.name AS connection_name', 'c.profile_key AS connection_profile_key')->andWhere('l.id = :mail_log_id')->setParameter('mail_log_id', $mailLogId)->setMaxResults(1);
        $row = $queryBuilder->executeQuery()->fetchAssociative();
        return is_array($row) ? $this->presenter->present($row) : null;
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
        $queryBuilder = $this->createQueryBuilder()->select('l.id', 'l.source', 'l.subject', 'l.status', 'l.final_connection_id', 'l.transport_message_id', 'l.last_error', 'l.recipients_json', 'l.payload_json', 'l.created_at', 'l.queued_at', 'l.sent_at', 'l.updated_at', 'c.name AS connection_name', 'c.profile_key AS connection_profile_key')->setFirstResult($query->offset())->setMaxResults($query->perPage);
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
        $queryBuilder = $this->createQueryBuilder()->select('l.status AS status', 'COUNT(*) AS total')->groupBy('l.status')->orderBy('l.status', 'ASC');
        $this->applyFilters($queryBuilder, $query->withoutFilter('statuses'));
        return $this->filterPresenter->statuses($queryBuilder->executeQuery()->fetchAllAssociative());
    }
    /**
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 1.0.9
     */
    private function connectionOptions(\JooosiMail\Admin\ReadModel\Log\LogQuery $query): array
    {
        $queryBuilder = $this->createQueryBuilder()->select('l.final_connection_id AS connection_id', 'c.name AS connection_name', 'COUNT(*) AS total')->groupBy('l.final_connection_id', 'c.name')->orderBy('c.name', 'ASC');
        $this->applyFilters($queryBuilder, $query->withoutFilter('connectionIds'));
        return $this->filterPresenter->connections($queryBuilder->executeQuery()->fetchAllAssociative());
    }
    /**
     * @since 1.0.9
     */
    private function createQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->from($this->tableNameResolver->resolve('mail_logs'), 'l')->leftJoin('l', $this->tableNameResolver->resolve('connections'), 'c', 'c.id = l.final_connection_id');
    }
    /**
     * @since 1.0.9
     */
    private function applyFilters(QueryBuilder $queryBuilder, \JooosiMail\Admin\ReadModel\Log\LogQuery $query): void
    {
        if ($query->search !== '') {
            $queryBuilder->andWhere('(LOWER(l.subject) LIKE :search OR LOWER(l.recipients_json) LIKE :search OR LOWER(l.payload_json) LIKE :search)')->setParameter('search', '%' . mb_strtolower($query->search) . '%');
        }
        $statuses = $query->filter('statuses');
        if ($statuses !== []) {
            $queryBuilder->andWhere('l.status IN (:statuses)')->setParameter('statuses', $statuses, ArrayParameterType::STRING);
        }
        $connectionIds = [];
        $includeUnassigned = \false;
        foreach ($query->filter('connectionIds') as $connectionId) {
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
                $conditions[] = 'l.final_connection_id IN (:connection_ids)';
                $queryBuilder->setParameter('connection_ids', array_values(array_unique($connectionIds)), ArrayParameterType::INTEGER);
            }
            if ($includeUnassigned) {
                $conditions[] = 'l.final_connection_id IS NULL';
            }
            $queryBuilder->andWhere('(' . implode(' OR ', $conditions) . ')');
        }
        $dateExpression = $this->dateTimeExpression();
        if ($query->fromDate !== null) {
            $queryBuilder->andWhere(sprintf('%s >= :from_date', $dateExpression))->setParameter('from_date', $query->fromDate . ' 00:00:00');
        }
        if ($query->toDate !== null) {
            $queryBuilder->andWhere(sprintf('%s <= :to_date', $dateExpression))->setParameter('to_date', $query->toDate . ' 23:59:59');
        }
    }
    /**
     * @since 1.0.9
     */
    private function applySorting(QueryBuilder $queryBuilder, string $sortBy, string $sortDirection): void
    {
        $sortColumn = match ($sortBy) {
            'id' => 'l.id',
            'subject' => 'l.subject',
            'status' => 'l.status',
            'connection' => 'COALESCE(c.name, \'\')',
            default => $this->dateTimeExpression(),
        };
        $queryBuilder->orderBy($sortColumn, $sortDirection)->addOrderBy('l.id', 'DESC');
    }
    /**
     * @since 1.0.9
     */
    private function dateTimeExpression(): string
    {
        return 'COALESCE(l.sent_at, l.queued_at, l.created_at, l.updated_at)';
    }
}

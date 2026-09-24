<?php

declare(strict_types=1);

namespace JooosiMail\Admin\ReadModel\Log;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Query\QueryBuilder;
use JooosiMail\Admin\Presentation\Log\WebhookEventPresenter;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;

/**
 * Reads and projects webhook log data for the admin API.
 *
 * @since 1.0.9
 */
#[Service]
final class WebhookLogReadModel
{
    public function __construct(
        private readonly DbalConnection $connection,
        private readonly TableNameResolver $tableNameResolver,
        private readonly WebhookEventPresenter $presenter,
        private readonly LogFilterPresenter $filterPresenter,
    ) {
    }

    /**
 * @since 1.0.9
     */
    public function search(LogQuery $query): LogPage
    {
        $total = $this->count($query);
        $totalPages = max(1, (int) ceil($total / $query->perPage));
        $effectiveQuery = $query->withPage(min($query->page, $totalPages));

        return LogPage::fromQuery(
            items: $this->presenter->presentMany($this->fetchRows($effectiveQuery)),
            total: $total,
            query: $effectiveQuery,
            filters: [
                'eventTypes' => $this->eventTypeOptions($query),
                'connections' => $this->connectionOptions($query),
            ],
        );
    }

    /**
 * @since 1.0.9
     */
    private function count(LogQuery $query): int
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
    private function fetchRows(LogQuery $query): array
    {
        $queryBuilder = $this->createQueryBuilder()
            ->select(
                'e.id',
                'e.connection_id',
                'e.mail_log_id',
                'e.event_type',
                'e.transport_message_id',
                'e.provider_event_id',
                'e.payload_json',
                'e.occurred_at',
                'e.created_at',
                'c.name AS connection_name',
                'c.profile_key AS connection_profile_key',
            )
            ->setFirstResult($query->offset())
            ->setMaxResults($query->perPage);

        $this->applyFilters($queryBuilder, $query);
        $this->applySorting($queryBuilder, $query->sortBy, $query->sortDirection);

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return list<array{label: string, value: string, count: int}>
     *
 * @since 1.0.9
     */
    private function eventTypeOptions(LogQuery $query): array
    {
        $queryBuilder = $this->createQueryBuilder()
            ->select('e.event_type AS event_type', 'COUNT(*) AS total')
            ->groupBy('e.event_type')
            ->orderBy('e.event_type', 'ASC');

        $this->applyFilters($queryBuilder, $query->withoutFilter('eventTypes'));

        return $this->filterPresenter->eventTypes($queryBuilder->executeQuery()->fetchAllAssociative());
    }

    /**
     * @return list<array{label: string, value: string, count: int}>
     *
 * @since 1.0.9
     */
    private function connectionOptions(LogQuery $query): array
    {
        $queryBuilder = $this->createQueryBuilder()
            ->select('e.connection_id AS connection_id', 'c.name AS connection_name', 'COUNT(*) AS total')
            ->groupBy('e.connection_id', 'c.name')
            ->orderBy('c.name', 'ASC');

        $this->applyFilters($queryBuilder, $query->withoutFilter('connectionIds'));

        return $this->filterPresenter->connections($queryBuilder->executeQuery()->fetchAllAssociative());
    }

    /**
 * @since 1.0.9
     */
    private function createQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from($this->tableNameResolver->resolve('webhook_events'), 'e')
            ->leftJoin('e', $this->tableNameResolver->resolve('connections'), 'c', 'c.id = e.connection_id');
    }

    /**
 * @since 1.0.9
     */
    private function applyFilters(QueryBuilder $queryBuilder, LogQuery $query): void
    {
        if ($query->search !== '') {
            $queryBuilder
                ->andWhere('(LOWER(e.event_type) LIKE :search OR LOWER(COALESCE(e.transport_message_id, \'\')) LIKE :search OR LOWER(COALESCE(e.provider_event_id, \'\')) LIKE :search OR LOWER(e.payload_json) LIKE :search OR LOWER(COALESCE(c.name, \'\')) LIKE :search)')
                ->setParameter('search', '%' . mb_strtolower($query->search) . '%');
        }

        $eventTypes = $query->filter('eventTypes');

        if ($eventTypes !== []) {
            $queryBuilder
                ->andWhere('e.event_type IN (:event_types)')
                ->setParameter('event_types', $eventTypes, ArrayParameterType::STRING);
        }

        $connectionIds = [];
        $includeUnassigned = false;

        foreach ($query->filter('connectionIds') as $connectionId) {
            if ($connectionId === 'unassigned') {
                $includeUnassigned = true;
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

        $dateExpression = $this->dateTimeExpression();

        if ($query->fromDate !== null) {
            $queryBuilder
                ->andWhere(sprintf('%s >= :webhook_from_date', $dateExpression))
                ->setParameter('webhook_from_date', $query->fromDate . ' 00:00:00');
        }

        if ($query->toDate !== null) {
            $queryBuilder
                ->andWhere(sprintf('%s <= :webhook_to_date', $dateExpression))
                ->setParameter('webhook_to_date', $query->toDate . ' 23:59:59');
        }
    }

    /**
 * @since 1.0.9
     */
    private function applySorting(QueryBuilder $queryBuilder, string $sortBy, string $sortDirection): void
    {
        $sortColumn = match ($sortBy) {
            'id' => 'e.id',
            'eventType' => 'e.event_type',
            'connection' => 'COALESCE(c.name, \'\')',
            'mailLogId' => 'COALESCE(e.mail_log_id, 0)',
            default => $this->dateTimeExpression(),
        };

        $queryBuilder
            ->orderBy($sortColumn, $sortDirection)
            ->addOrderBy('e.id', 'DESC');
    }

    /**
 * @since 1.0.9
     */
    private function dateTimeExpression(): string
    {
        return 'COALESCE(e.occurred_at, e.created_at)';
    }
}

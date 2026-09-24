<?php

declare(strict_types=1);

namespace JooosiMail\Admin\ReadModel\Log;

/**
 * Immutable, normalized query parameters shared by admin log read models.
 *
 * @since 1.0.9
 */
final class LogQuery
{
    /**
     * @param array<string, list<string>> $filters
     *
 * @since 1.0.9
     */
    public function __construct(
        public readonly string $search,
        public readonly ?string $fromDate,
        public readonly ?string $toDate,
        public readonly int $page,
        public readonly int $perPage,
        public readonly string $sortBy,
        public readonly string $sortDirection,
        private readonly array $filters = [],
    ) {
    }

    /**
     * Creates a value object from the output of LogQueryNormalizer.
     *
     * @param array<string, mixed> $query
     *
 * @since 1.0.9
     */
    public static function fromArray(array $query): self
    {
        $filters = [];
        $queryKeys = [
            'search',
            'fromDate',
            'toDate',
            'page',
            'perPage',
            'sortBy',
            'sortDirection',
        ];

        foreach ($query as $name => $values) {
            if (in_array($name, $queryKeys, true) || ! is_array($values)) {
                continue;
            }

            $filters[$name] = [];

            foreach ($values as $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $value = trim((string) $value);

                if ($value !== '') {
                    $filters[$name][] = $value;
                }
            }
        }

        return new self(
            search: is_scalar($query['search'] ?? null) ? (string) $query['search'] : '',
            fromDate: is_string($query['fromDate'] ?? null) ? $query['fromDate'] : null,
            toDate: is_string($query['toDate'] ?? null) ? $query['toDate'] : null,
            page: max(1, (int) ($query['page'] ?? 1)),
            perPage: min(100, max(1, (int) ($query['perPage'] ?? 25))),
            sortBy: is_scalar($query['sortBy'] ?? null) ? (string) $query['sortBy'] : 'dateTime',
            sortDirection: strtoupper((string) ($query['sortDirection'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
            filters: $filters,
        );
    }

    /**
     * Returns one named filter list.
     *
     * @return list<string>
     *
 * @since 1.0.9
     */
    public function filter(string $name): array
    {
        return $this->filters[$name] ?? [];
    }

    /**
     * Returns all filter lists.
     *
     * @return array<string, list<string>>
     *
 * @since 1.0.9
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * Returns an equivalent query with one filter removed.
     *
 * @since 1.0.9
     */
    public function withoutFilter(string $name): self
    {
        $filters = $this->filters;
        unset($filters[$name]);

        return new self(
            search: $this->search,
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            page: $this->page,
            perPage: $this->perPage,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            filters: $filters,
        );
    }

    /**
     * Returns an equivalent query for a different page.
     *
 * @since 1.0.9
     */
    public function withPage(int $page): self
    {
        return new self(
            search: $this->search,
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            page: max(1, $page),
            perPage: $this->perPage,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            filters: $this->filters,
        );
    }

    /**
     * Returns the SQL offset for this query.
     *
 * @since 1.0.9
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}

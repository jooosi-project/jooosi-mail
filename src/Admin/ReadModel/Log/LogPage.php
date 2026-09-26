<?php

declare (strict_types=1);
namespace JooosiMail\Admin\ReadModel\Log;

/**
 * Immutable paginated result for an admin log endpoint.
 *
 * @since 1.0.9
 */
final class LogPage
{
    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, list<array{label: string, value: string, count: int}>> $filters
     *
     * @since 1.0.9
     */
    private function __construct(private readonly array $items, private readonly int $page, private readonly int $perPage, private readonly int $total, private readonly int $totalPages, private readonly array $filters)
    {
    }
    /**
     * Creates a result from the requested query and total matching rows.
     *
     * @param list<array<string, mixed>> $items
     * @param array<string, list<array{label: string, value: string, count: int}>> $filters
     *
     * @since 1.0.9
     */
    public static function fromQuery(array $items, int $total, \JooosiMail\Admin\ReadModel\Log\LogQuery $query, array $filters = []): self
    {
        $total = max(0, $total);
        $totalPages = max(1, (int) ceil($total / $query->perPage));
        return new self(items: $items, page: min($query->page, $totalPages), perPage: $query->perPage, total: $total, totalPages: $totalPages, filters: $filters);
    }
    /**
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function items(): array
    {
        return $this->items;
    }
    /**
     * @return array{page: int, perPage: int, total: int, totalPages: int}
     *
     * @since 1.0.9
     */
    public function pagination(): array
    {
        return ['page' => $this->page, 'perPage' => $this->perPage, 'total' => $this->total, 'totalPages' => $this->totalPages];
    }
    /**
     * @return array<string, list<array{label: string, value: string, count: int}>>
     *
     * @since 1.0.9
     */
    public function filters(): array
    {
        return $this->filters;
    }
}

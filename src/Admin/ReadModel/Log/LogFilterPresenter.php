<?php

declare (strict_types=1);
namespace JooosiMail\Admin\ReadModel\Log;

use JooosiMail\Discovery\Attribute\Service;
use function JooosiMailDeps\Symfony\Component\String\u;
/**
 * Converts grouped database filter rows into the public admin API shape.
 *
 * @since 1.0.9
 */
#[Service]
final class LogFilterPresenter
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 1.0.9
     */
    public function statuses(array $rows): array
    {
        return $this->values($rows, 'status');
    }
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 1.0.9
     */
    public function eventTypes(array $rows): array
    {
        return $this->values($rows, 'event_type');
    }
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 1.0.9
     */
    public function connections(array $rows): array
    {
        return array_map(static fn(array $row): array => ['label' => isset($row['connection_name']) && $row['connection_name'] !== '' ? (string) $row['connection_name'] : 'Unassigned', 'value' => isset($row['connection_id']) && is_numeric($row['connection_id']) ? (string) (int) $row['connection_id'] : 'unassigned', 'count' => (int) ($row['total'] ?? 0)], $rows);
    }
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 1.0.9
     */
    private function values(array $rows, string $column): array
    {
        return array_map(static fn(array $row): array => ['label' => u((string) ($row[$column] ?? ''))->replace('_', ' ')->replace('-', ' ')->title(\true)->toString(), 'value' => (string) ($row[$column] ?? ''), 'count' => (int) ($row['total'] ?? 0)], $rows);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Admin\ReadModel;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\Database\TableNameResolver;
/**
 * Reads date-bucketed sending statistics for the admin dashboard.
 *
 * @since 1.0.9
 */
#[Service]
final class DashboardReadModel
{
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver)
    {
    }
    /**
     * @return list<array{date: string, total: int, sent: int, failed: int}>
     *
     * @since 1.0.9
     */
    public function sendingStats(mixed $fromDateParam, mixed $toDateParam): array
    {
        $fromDate = is_string($fromDateParam) ? $this->normalizeLogDate($fromDateParam) : null;
        $toDate = is_string($toDateParam) ? $this->normalizeLogDate($toDateParam) : null;
        [$startDate, $endDate] = $this->resolveSendingStatsDateRange($fromDate, $toDate);
        $dateExpression = 'COALESCE(sent_at, queued_at, created_at, updated_at)';
        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT
                DATE(%1$s) AS stat_date,
                COUNT(*) AS total,
                SUM(CASE WHEN status = :sent_status THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = :failed_status THEN 1 ELSE 0 END) AS failed
            FROM %2$s
            WHERE %1$s >= :start_date
                AND %1$s <= :end_date
            GROUP BY DATE(%1$s)
            ORDER BY stat_date ASC', $dateExpression, $this->tableNameResolver->resolve('mail_logs')), ['sent_status' => 'sent', 'failed_status' => 'failed', 'start_date' => $startDate->format('Y-m-d 00:00:00'), 'end_date' => $endDate->format('Y-m-d 23:59:59')]);
        $rowsByDate = [];
        foreach ($rows as $row) {
            $date = (string) ($row['stat_date'] ?? '');
            if ($date === '') {
                continue;
            }
            $rowsByDate[$date] = ['total' => (int) ($row['total'] ?? 0), 'sent' => (int) ($row['sent'] ?? 0), 'failed' => (int) ($row['failed'] ?? 0)];
        }
        $stats = [];
        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate->add(new DateInterval('P1D')));
        foreach ($period as $date) {
            $dateKey = $date->format('Y-m-d');
            $row = $rowsByDate[$dateKey] ?? ['total' => 0, 'sent' => 0, 'failed' => 0];
            $stats[] = ['date' => $dateKey, 'total' => $row['total'], 'sent' => $row['sent'], 'failed' => $row['failed']];
        }
        return $stats;
    }
    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     *
     * @since 1.0.9
     */
    private function resolveSendingStatsDateRange(?string $fromDate, ?string $toDate): array
    {
        $endDate = $toDate !== null ? new DateTimeImmutable($toDate . ' 00:00:00') : new DateTimeImmutable(gmdate('Y-m-d 00:00:00'));
        $startDate = $fromDate !== null ? new DateTimeImmutable($fromDate . ' 00:00:00') : $endDate->sub(new DateInterval('P89D'));
        if ($startDate > $endDate) {
            return [$endDate, $startDate];
        }
        return [$startDate, $endDate];
    }
    /**
     * @since 1.0.9
     */
    private function normalizeLogDate(string $value): ?string
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
}

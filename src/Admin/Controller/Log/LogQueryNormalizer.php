<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller\Log;

use JooosiMail\Discovery\Attribute\Service;
use WP_REST_Request;

/**
 * Normalizes the shared query parameters accepted by admin log endpoints.
 *
 * @since 1.0.9
 */
#[Service]
final class LogQueryNormalizer
{
    /**
     * @param list<string> $sortColumns Allowed public sort keys.
     * @param list<string> $arrayFilters Filter names accepted as lists.
     *
     * @return array<string, int|string|list<string>|null>
     *
     * @since 1.0.9
     */
    public function normalize(WP_REST_Request $request, array $sortColumns, array $arrayFilters): array
    {
        $perPage = (int) $this->normalizeString($request->get_param('perPage'));
        $sortBy = $this->normalizeString($request->get_param('sortBy'));
        $sortDirection = strtoupper($this->normalizeString($request->get_param('sortDirection')));
        $query = [
            'search' => trim($this->normalizeString($request->get_param('search'))),
            'fromDate' => $this->normalizeDate($request->get_param('fromDate')),
            'toDate' => $this->normalizeDate($request->get_param('toDate')),
            'page' => max(1, (int) $this->normalizeString($request->get_param('page'))),
            'perPage' => min(100, max(1, $perPage > 0 ? $perPage : 25)),
            'sortBy' => in_array($sortBy, $sortColumns, true) ? $sortBy : 'dateTime',
            'sortDirection' => $sortDirection === 'ASC' ? 'ASC' : 'DESC',
        ];

        foreach ($arrayFilters as $filter) {
            $query[$filter] = $this->normalizeStringList($request->get_param($filter));
        }

        return $query;
    }

    /**
     * @since 1.0.9
     */
    private function normalizeString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     *
     * @since 1.0.9
     */
    private function normalizeStringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $value = trim($this->normalizeString($value));

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @since 1.0.9
     */
    private function normalizeDate(mixed $value): ?string
    {
        $value = trim($this->normalizeString($value));

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return $value;
    }
}

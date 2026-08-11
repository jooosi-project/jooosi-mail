<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Controller\Log;

use JooosiMailDeps\Doctrine\DBAL\ArrayParameterType;
use JooosiMailDeps\Doctrine\DBAL\Connection as DbalConnection;
use JooosiMailDeps\Doctrine\DBAL\Query\QueryBuilder;
use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Admin\Mail\TestEmailSender;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Infrastructure\Database\TableNameResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use function JooosiMailDeps\Symfony\Component\String\u;
/**
 * Serves mail log data for the admin UI.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/logs/mail')]
final class MailController
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly DbalConnection $connection, private readonly TableNameResolver $tableNameResolver, private readonly TestEmailSender $testEmailSender)
    {
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $query = $this->normalizeMailLogQuery($request);
        $total = $this->countMailLogs($query);
        $perPage = $query['perPage'];
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($query['page'], $totalPages);
        $query['page'] = $page;
        return new WP_REST_Response(['items' => $this->normalizeMailLogs($this->queryMailLogs($query)), 'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages], 'filters' => ['statuses' => $this->getMailLogStatusOptions($query), 'connections' => $this->getMailLogConnectionOptions($query)]]);
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<mail_log_id>\d+)', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $mailLogId = max(1, (int) $request->get_param('mail_log_id'));
        $mailLog = $this->findMailLogById($mailLogId);
        return new WP_REST_Response(['item' => $mailLog !== null ? $this->normalizeMailLogs([$mailLog])[0] : null]);
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '/test', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function sendTest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $to = sanitize_email((string) $request->get_param('to'));
        if ($to === '' || !is_email($to)) {
            return new WP_Error('jooosi_mail_invalid_test_email', __('Enter a valid recipient email address.', 'jooosi-mail'), ['status' => 400]);
        }
        $subject = (string) $request->get_param('subject');
        $connectionId = max(0, (int) $request->get_param('connectionId'));
        $sent = $this->testEmailSender->send($to, $subject, $connectionId);
        if (!$sent) {
            return new WP_Error('jooosi_mail_test_email_failed', __('The test email failed.', 'jooosi-mail'), ['status' => 500]);
        }
        return new WP_REST_Response(['sent' => \true, 'message' => __('The test email was queued or sent successfully.', 'jooosi-mail')]);
    }
    /**
     * @param array<string, mixed> $query
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function queryMailLogs(array $query): array
    {
        $queryBuilder = $this->createMailLogQueryBuilder()->select('l.id', 'l.source', 'l.subject', 'l.status', 'l.final_connection_id', 'l.transport_message_id', 'l.last_error', 'l.recipients_json', 'l.payload_json', 'l.created_at', 'l.queued_at', 'l.sent_at', 'l.updated_at', 'c.name AS connection_name')->setFirstResult(($query['page'] - 1) * $query['perPage'])->setMaxResults($query['perPage']);
        $this->applyMailLogFilters($queryBuilder, $query);
        $this->applyMailLogSorting($queryBuilder, $query['sortBy'], $query['sortDirection']);
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }
    /**
     * @return array<string, mixed>|null
     *
     * @since 0.1.0
     */
    private function findMailLogById(int $mailLogId): ?array
    {
        $queryBuilder = $this->createMailLogQueryBuilder()->select('l.id', 'l.source', 'l.subject', 'l.status', 'l.final_connection_id', 'l.transport_message_id', 'l.last_error', 'l.recipients_json', 'l.payload_json', 'l.created_at', 'l.queued_at', 'l.sent_at', 'l.updated_at', 'c.name AS connection_name')->andWhere('l.id = :mail_log_id')->setParameter('mail_log_id', $mailLogId)->setMaxResults(1);
        $row = $queryBuilder->executeQuery()->fetchAssociative();
        return is_array($row) ? $row : null;
    }
    /**
     * @param array<string, mixed> $query
     *
     * @since 0.1.0
     */
    private function countMailLogs(array $query): int
    {
        $queryBuilder = $this->createMailLogQueryBuilder()->select('COUNT(*)');
        $this->applyMailLogFilters($queryBuilder, $query);
        return (int) $queryBuilder->executeQuery()->fetchOne();
    }
    /**
     * @param array<string, mixed> $query
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 0.1.0
     */
    private function getMailLogStatusOptions(array $query): array
    {
        $filterQuery = $query;
        $filterQuery['statuses'] = [];
        $queryBuilder = $this->createMailLogQueryBuilder()->select('l.status AS status', 'COUNT(*) AS total')->groupBy('l.status')->orderBy('l.status', 'ASC');
        $this->applyMailLogFilters($queryBuilder, $filterQuery);
        return array_map(static fn(array $row): array => ['label' => u((string) ($row['status'] ?? ''))->replace('_', ' ')->replace('-', ' ')->title(\true)->toString(), 'value' => (string) ($row['status'] ?? ''), 'count' => (int) ($row['total'] ?? 0)], $queryBuilder->executeQuery()->fetchAllAssociative());
    }
    /**
     * @param array<string, mixed> $query
     * @return list<array{label: string, value: string, count: int}>
     *
     * @since 0.1.0
     */
    private function getMailLogConnectionOptions(array $query): array
    {
        $filterQuery = $query;
        $filterQuery['connectionIds'] = [];
        $queryBuilder = $this->createMailLogQueryBuilder()->select('l.final_connection_id AS connection_id', 'c.name AS connection_name', 'COUNT(*) AS total')->groupBy('l.final_connection_id', 'c.name')->orderBy('c.name', 'ASC');
        $this->applyMailLogFilters($queryBuilder, $filterQuery);
        return array_map(static fn(array $row): array => ['label' => isset($row['connection_name']) && $row['connection_name'] !== '' ? (string) $row['connection_name'] : 'Unassigned', 'value' => isset($row['connection_id']) && is_numeric($row['connection_id']) ? (string) (int) $row['connection_id'] : 'unassigned', 'count' => (int) ($row['total'] ?? 0)], $queryBuilder->executeQuery()->fetchAllAssociative());
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function normalizeMailLogQuery(WP_REST_Request $request): array
    {
        $perPage = (int) $request->get_param('perPage');
        $sortBy = (string) $request->get_param('sortBy');
        $sortDirection = strtoupper((string) $request->get_param('sortDirection'));
        $statuses = $request->get_param('statuses');
        $connectionIds = $request->get_param('connectionIds');
        if (!in_array($sortBy, ['id', 'subject', 'status', 'dateTime', 'connection'], \true)) {
            $sortBy = 'dateTime';
        }
        return ['search' => trim((string) $request->get_param('search')), 'statuses' => is_array($statuses) ? array_values(array_filter(array_map(static fn(mixed $status): string => trim((string) $status), $statuses), static fn(string $status): bool => $status !== '')) : [], 'connectionIds' => is_array($connectionIds) ? array_values(array_filter(array_map(static fn(mixed $connectionId): string => trim((string) $connectionId), $connectionIds), static fn(string $connectionId): bool => $connectionId !== '')) : [], 'fromDate' => self::normalizeLogDate((string) $request->get_param('fromDate')), 'toDate' => self::normalizeLogDate((string) $request->get_param('toDate')), 'page' => max(1, (int) $request->get_param('page')), 'perPage' => min(100, max(1, $perPage > 0 ? $perPage : 25)), 'sortBy' => $sortBy, 'sortDirection' => $sortDirection === 'ASC' ? 'ASC' : 'DESC'];
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
    private function createMailLogQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->from($this->tableNameResolver->resolve('mail_logs'), 'l')->leftJoin('l', $this->tableNameResolver->resolve('connections'), 'c', 'c.id = l.final_connection_id');
    }
    /**
     * @param array<string, mixed> $query
     *
     * @since 0.1.0
     */
    private function applyMailLogFilters(QueryBuilder $queryBuilder, array $query): void
    {
        if ($query['search'] !== '') {
            $queryBuilder->andWhere('(LOWER(l.subject) LIKE :search OR LOWER(l.recipients_json) LIKE :search OR LOWER(l.payload_json) LIKE :search)')->setParameter('search', '%' . mb_strtolower((string) $query['search']) . '%');
        }
        if ($query['statuses'] !== []) {
            $queryBuilder->andWhere('l.status IN (:statuses)')->setParameter('statuses', $query['statuses'], ArrayParameterType::STRING);
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
                $conditions[] = 'l.final_connection_id IN (:connection_ids)';
                $queryBuilder->setParameter('connection_ids', array_values(array_unique($connectionIds)), ArrayParameterType::INTEGER);
            }
            if ($includeUnassigned) {
                $conditions[] = 'l.final_connection_id IS NULL';
            }
            $queryBuilder->andWhere('(' . implode(' OR ', $conditions) . ')');
        }
        $dateExpression = $this->getMailLogDateTimeExpression();
        if ($query['fromDate'] !== null) {
            $queryBuilder->andWhere(sprintf('%s >= :from_date', $dateExpression))->setParameter('from_date', $query['fromDate'] . ' 00:00:00');
        }
        if ($query['toDate'] !== null) {
            $queryBuilder->andWhere(sprintf('%s <= :to_date', $dateExpression))->setParameter('to_date', $query['toDate'] . ' 23:59:59');
        }
    }
    /**
     * @since 0.1.0
     */
    private function applyMailLogSorting(QueryBuilder $queryBuilder, string $sortBy, string $sortDirection): void
    {
        $sortColumn = match ($sortBy) {
            'id' => 'l.id',
            'subject' => 'l.subject',
            'status' => 'l.status',
            'connection' => 'COALESCE(c.name, \'\')',
            default => $this->getMailLogDateTimeExpression(),
        };
        $queryBuilder->orderBy($sortColumn, $sortDirection)->addOrderBy('l.id', 'DESC');
    }
    /**
     * @since 0.1.0
     */
    private function getMailLogDateTimeExpression(): string
    {
        return 'COALESCE(l.sent_at, l.queued_at, l.created_at, l.updated_at)';
    }
    /**
     * @param list<array<string, mixed>> $logs
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeMailLogs(array $logs): array
    {
        return array_map(function (array $log): array {
            $messageBodies = $this->extractPayloadBodies($log['payload_json'] ?? null);
            return ['id' => (int) ($log['id'] ?? 0), 'source' => (string) ($log['source'] ?? ''), 'subject' => (string) ($log['subject'] ?? ''), 'status' => (string) ($log['status'] ?? ''), 'finalConnectionId' => isset($log['final_connection_id']) ? (int) $log['final_connection_id'] : null, 'connectionName' => isset($log['connection_name']) ? (string) $log['connection_name'] : null, 'transportMessageId' => isset($log['transport_message_id']) ? (string) $log['transport_message_id'] : null, 'lastError' => isset($log['last_error']) ? (string) $log['last_error'] : null, 'toAddresses' => $this->extractAddressList($this->decodeJsonArray($log['recipients_json'] ?? null)), 'fromAddresses' => $this->extractAddressListFromPayload($log['payload_json'] ?? null, 'from'), 'ccAddresses' => $this->extractAddressListFromPayload($log['payload_json'] ?? null, 'cc'), 'bccAddresses' => $this->extractAddressListFromPayload($log['payload_json'] ?? null, 'bcc'), 'replyToAddresses' => $this->extractAddressListFromPayload($log['payload_json'] ?? null, 'replyTo'), 'textBody' => $messageBodies['textBody'], 'htmlBody' => $messageBodies['htmlBody'], 'createdAt' => isset($log['created_at']) ? (string) $log['created_at'] : null, 'queuedAt' => isset($log['queued_at']) ? (string) $log['queued_at'] : null, 'sentAt' => isset($log['sent_at']) ? (string) $log['sent_at'] : null, 'updatedAt' => isset($log['updated_at']) ? (string) $log['updated_at'] : null];
        }, $logs);
    }
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function extractAddressListFromPayload(mixed $payloadJson, string $key): array
    {
        $payload = $this->decodeJsonArray($payloadJson);
        if ($payload === []) {
            return [];
        }
        return $this->extractAddressList(is_array($payload[$key] ?? null) ? $payload[$key] : []);
    }
    /**
     * @since 0.1.0
     */
    private function extractPayloadString(mixed $payloadJson, string $key): ?string
    {
        $payload = $this->decodeJsonArray($payloadJson);
        if ($payload === []) {
            return null;
        }
        $value = $payload[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
    /**
     * @return array{textBody: ?string, htmlBody: ?string}
     *
     * @since 0.1.0
     */
    private function extractPayloadBodies(mixed $payloadJson): array
    {
        $textBody = $this->extractPayloadString($payloadJson, 'textBody');
        $htmlBody = $this->extractPayloadString($payloadJson, 'htmlBody');
        if ($htmlBody === null && $textBody !== null && $this->payloadUsesHtmlContentType($payloadJson)) {
            return ['textBody' => null, 'htmlBody' => $textBody];
        }
        return ['textBody' => $textBody, 'htmlBody' => $htmlBody];
    }
    /**
     * @since 0.1.0
     */
    private function payloadUsesHtmlContentType(mixed $payloadJson): bool
    {
        $payload = $this->decodeJsonArray($payloadJson);
        if ($payload === []) {
            return \false;
        }
        $contentType = $this->extractContentTypeHeader($payload['headers'] ?? null) ?? $this->extractContentTypeHeader($payload['metadata']['raw']['headers'] ?? null);
        if ($contentType === null) {
            return \false;
        }
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0] ?? $contentType));
        return 'text/html' === $mediaType;
    }
    /**
     * @since 0.1.0
     */
    private function extractContentTypeHeader(mixed $headers): ?string
    {
        if (is_array($headers)) {
            $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? null;
            if (is_string($contentType) && trim($contentType) !== '') {
                return trim($contentType);
            }
            foreach ($headers as $headerLine) {
                if (!is_string($headerLine)) {
                    continue;
                }
                $normalizedHeaderLine = trim($headerLine);
                if (!str_starts_with(strtolower($normalizedHeaderLine), 'content-type:')) {
                    continue;
                }
                $value = trim(substr($normalizedHeaderLine, strlen('content-type:')));
                return $value !== '' ? $value : null;
            }
            return null;
        }
        if (!is_string($headers) || trim($headers) === '') {
            return null;
        }
        foreach (preg_split('/\r\n|\r|\n/', $headers) ?: [] as $headerLine) {
            $normalizedHeaderLine = trim((string) $headerLine);
            if (!str_starts_with(strtolower($normalizedHeaderLine), 'content-type:')) {
                continue;
            }
            $value = trim(substr($normalizedHeaderLine, strlen('content-type:')));
            return $value !== '' ? $value : null;
        }
        return null;
    }
    /**
     * @param array<int, array<string, mixed>> $addresses
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function extractAddressList(array $addresses): array
    {
        $results = [];
        foreach ($addresses as $address) {
            $emailAddress = isset($address['address']) ? trim((string) $address['address']) : '';
            if ($emailAddress === '') {
                continue;
            }
            $results[] = $emailAddress;
        }
        return $results;
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, \true);
        return is_array($decoded) ? $decoded : [];
    }
}

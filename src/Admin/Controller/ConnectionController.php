<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller;

use JooosiMail\Admin\Connection\AdminConnectionPayloadFactory;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionConfigurationException;
use JooosiMail\Mail\Connection\ConnectionManager;
use JooosiMail\Mail\Connection\ConnectionRepository;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles admin CRUD requests for connections.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/connections')]
final class ConnectionController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly ConnectionRepository $connectionRepository,
        private readonly ConnectionManager $connectionManager,
        private readonly ConnectionStatusReporter $connectionStatusReporter,
        private readonly AdminConnectionPayloadFactory $connectionPayloadFactory,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $statusMap = $this->getStatusMap();
        $connections = [];

        foreach ($this->connectionRepository->findAll() as $connection) {
            $connections[] = $this->createConnectionListPayload($connection, $statusMap);
        }

        return new WP_REST_Response([
            'profiles' => $this->createProfilePayloads(),
            'connections' => $connections,
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $connection = $this->connectionManager->create($this->normalizeConnectionInput($request));
        } catch (ConnectionConfigurationException $exception) {
            return new WP_Error('jooosi_mail_invalid_connection', $exception->getMessage(), ['status' => 400]);
        }

        return new WP_REST_Response([
            'connection' => $this->createConnectionDetailPayload($connection),
        ], 201);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        return new WP_REST_Response([
            'connection' => $this->createConnectionDetailPayload($connection),
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)', methods: ['PUT', 'PATCH'], permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        try {
            $updatedConnection = $this->connectionManager->update(
                (int) $connection->id,
                $this->normalizeConnectionInput($request),
            );
        } catch (ConnectionConfigurationException $exception) {
            return new WP_Error('jooosi_mail_invalid_connection', $exception->getMessage(), ['status' => 400]);
        }

        return new WP_REST_Response([
            'connection' => $this->createConnectionDetailPayload($updatedConnection),
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)', methods: 'DELETE', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $this->connectionManager->delete((int) $connection->id);

        return new WP_REST_Response([
            'deleted' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)/default', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function makeDefault(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $defaultConnection = $this->connectionManager->setDefault((int) $connection->id);

        return new WP_REST_Response([
            'connection' => $this->createConnectionDetailPayload($defaultConnection),
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<connection_id>\d+)/enabled', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function setEnabled(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $connection = $this->resolveConnection($request);

        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $body = $request->get_json_params();
        $input = is_array($body) ? $body : $request->get_params();

        if (! array_key_exists('enabled', $input)) {
            return new WP_Error('jooosi_mail_missing_enabled', 'The enabled field is required.', ['status' => 400]);
        }

        $enabled = filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($enabled === null) {
            return new WP_Error('jooosi_mail_invalid_enabled', 'The enabled field must be a boolean.', ['status' => 400]);
        }

        try {
            $updatedConnection = $this->connectionManager->setEnabled((int) $connection->id, $enabled);
        } catch (ConnectionConfigurationException $exception) {
            return new WP_Error('jooosi_mail_invalid_connection', $exception->getMessage(), ['status' => 400]);
        }

        return new WP_REST_Response([
            'connection' => $this->createConnectionDetailPayload($updatedConnection),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function createProfilePayloads(): array
    {
        $profiles = $this->connectionManager->listProfiles();

        usort($profiles, static fn (array $left, array $right): int => strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? '')));

        return array_map(function (array $profile): array {
            $configurationFields = [];

            foreach ((array) ($profile['configuration_fields'] ?? []) as $fieldName => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $configurationFields[] = [
                    'name' => (string) $fieldName,
                    'label' => (string) ($field['label'] ?? $fieldName),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'required' => (bool) ($field['required'] ?? false),
                    'secret' => (($field['type'] ?? null) === 'password') || (($field['secret'] ?? false) === true),
                    'default' => $field['default'] ?? null,
                    'choices' => array_values(array_map('strval', is_array($field['choices'] ?? null) ? $field['choices'] : [])),
                    'visibleWhen' => $this->normalizeFieldConditions($field['visible_when'] ?? null),
                    'requiredWhen' => $this->normalizeFieldConditions($field['required_when'] ?? null),
                ];
            }

            $payload = [
                'key' => (string) ($profile['key'] ?? ''),
                'label' => (string) ($profile['label'] ?? ''),
                'description' => (string) ($profile['description'] ?? ''),
                'schemes' => array_values(array_map('strval', is_array($profile['schemes'] ?? null) ? $profile['schemes'] : [])),
                'supportsWebhooks' => (bool) ($profile['supports_webhooks'] ?? false),
                'configurationFields' => $configurationFields,
            ];

            if (is_array($profile['metadata'] ?? null) && $profile['metadata'] !== []) {
                $payload['metadata'] = $profile['metadata'];
            }

            return $payload;
        }, $profiles);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function normalizeFieldConditions(mixed $conditionSet): array
    {
        if (! is_array($conditionSet)) {
            return [];
        }

        $normalized = [];

        foreach ($conditionSet as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $fieldName = isset($condition['field']) ? trim((string) $condition['field']) : '';

            if ($fieldName === '') {
                continue;
            }

            $operator = strtolower(trim((string) ($condition['operator'] ?? 'in')));

            if (! in_array($operator, ['in', 'not_in'], true)) {
                continue;
            }

            $normalized[] = [
                'field' => $fieldName,
                'operator' => $operator,
                'values' => array_values(array_map('strval', is_array($condition['values'] ?? null) ? $condition['values'] : [])),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $statusMap
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function createConnectionListPayload(Connection $connection, array $statusMap): array
    {
        $payload = $this->connectionPayloadFactory->createList($connection);

        return $this->withStatusPayload($payload, $connection, $statusMap[$connection->id ?? 0] ?? null);
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function createConnectionDetailPayload(Connection $connection): array
    {
        $payload = $this->connectionPayloadFactory->createDetail($connection);
        $payload['dsn'] = $connection->dsn;
        $payload['rateLimits'] = [
            'minute' => $this->extractConnectionSetting($connection, ['rate_limits', 'minute'], 'rate_limit_per_minute'),
            'hour' => $this->extractConnectionSetting($connection, ['rate_limits', 'hour'], 'rate_limit_per_hour'),
            'day' => $this->extractConnectionSetting($connection, ['rate_limits', 'day'], 'rate_limit_per_day'),
        ];
        $payload['circuitBreaker'] = [
            'threshold' => $this->extractConnectionSetting($connection, ['circuit_breaker', 'threshold'], 'circuit_breaker_threshold'),
            'window' => $this->extractConnectionSetting($connection, ['circuit_breaker', 'window'], 'circuit_breaker_window'),
            'cooldown' => $this->extractConnectionSetting($connection, ['circuit_breaker', 'cooldown'], 'circuit_breaker_cooldown'),
        ];
        $payload['sender'] = $this->extractSenderSettings($connection);

        return $this->withStatusPayload($payload, $connection, $this->getStatusMap()[$connection->id ?? 0] ?? null);
    }

    /**
     * @param array<string, mixed>      $payload
     * @param array<string, mixed>|null $status
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function withStatusPayload(array $payload, Connection $connection, ?array $status): array
    {
        $availability = is_array($status['availability'] ?? null) ? $status['availability'] : [];
        $circuitBreaker = is_array($availability['circuit_breaker'] ?? null) ? $availability['circuit_breaker'] : [];
        $rateLimit = is_array($availability['rate_limit'] ?? null) ? $availability['rate_limit'] : [];

        $payload['healthScore'] = (int) ($status['health_score'] ?? 0);
        $payload['available'] = (bool) ($availability['available'] ?? $connection->enabled);
        $payload['unavailableReasons'] = array_values(array_map('strval', is_array($availability['unavailable_reasons'] ?? null) ? $availability['unavailable_reasons'] : []));
        $payload['nextAvailableAt'] = $this->normalizeDateTime($availability['next_available_at'] ?? null);
        $payload['webhookUrl'] = $connection->id !== null ? rest_url('jooosi-mail/v1/webhook/' . $connection->id) : null;
        $payload['rateLimitStatus'] = [
            'blocked' => (bool) ($rateLimit['blocked'] ?? false),
            'windows' => is_array($rateLimit['windows'] ?? null) ? $rateLimit['windows'] : [],
        ];
        $payload['circuitBreakerStatus'] = [
            'enabled' => (bool) ($circuitBreaker['enabled'] ?? false),
            'recentFailures' => (int) ($circuitBreaker['recent_failures'] ?? 0),
            'blacklistedUntil' => $this->normalizeDateTime($circuitBreaker['blacklisted_until'] ?? null),
        ];

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function getStatusMap(): array
    {
        $map = [];

        foreach ($this->connectionStatusReporter->getStatuses(true) as $status) {
            $connection = $status['connection'];

            if ($connection->id === null) {
                continue;
            }

            $map[$connection->id] = $status;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function normalizeConnectionInput(WP_REST_Request $request): array
    {
        $body = $request->get_json_params();
        $input = is_array($body) ? $body : $request->get_params();
        $normalized = [
            'profile' => isset($input['profile']) ? (string) $input['profile'] : '',
            'name' => isset($input['name']) ? (string) $input['name'] : '',
            'dsn' => isset($input['dsn']) ? (string) $input['dsn'] : '',
            'enabled' => (bool) ($input['enabled'] ?? true),
            'default' => (bool) ($input['default'] ?? false),
            'priority' => (int) ($input['priority'] ?? 10),
            'weight' => (int) ($input['weight'] ?? 1),
            'webhook_enabled' => (bool) ($input['webhookEnabled'] ?? false),
        ];

        $rateLimits = is_array($input['rateLimits'] ?? null) ? $input['rateLimits'] : [];
        $circuitBreaker = is_array($input['circuitBreaker'] ?? null) ? $input['circuitBreaker'] : [];
        $sender = is_array($input['sender'] ?? null) ? $input['sender'] : null;

        foreach (['minute', 'hour', 'day'] as $period) {
            if (array_key_exists($period, $rateLimits)) {
                $normalized['rate_limit_' . $period] = $rateLimits[$period];
            }
        }

        foreach (['threshold', 'window', 'cooldown'] as $key) {
            if (array_key_exists($key, $circuitBreaker)) {
                $normalized['circuit_' . $key] = $circuitBreaker[$key];
            }
        }

        if ($sender !== null) {
            $normalized['sender'] = [
                'email' => isset($sender['email']) ? (string) $sender['email'] : '',
                'name' => isset($sender['name']) ? (string) $sender['name'] : '',
                'force_email' => (bool) ($sender['forceEmail'] ?? false),
                'force_name' => (bool) ($sender['forceName'] ?? false),
                'return_path_mode' => isset($sender['returnPathMode']) ? (string) $sender['returnPathMode'] : 'inherit',
                'return_path_email' => isset($sender['returnPathEmail']) ? (string) $sender['returnPathEmail'] : '',
            ];
        }

        $configuration = is_array($input['configuration'] ?? null) ? $input['configuration'] : [];

        foreach ($configuration as $fieldName => $value) {
            if (is_string($fieldName)) {
                $normalized[$fieldName] = $value;
            }
        }

        $secretConfiguration = is_array($input['secretConfiguration'] ?? null) ? $input['secretConfiguration'] : [];

        foreach ($secretConfiguration as $fieldName => $secretInput) {
            if (! is_string($fieldName) || ! is_array($secretInput)) {
                continue;
            }

            $action = (string) ($secretInput['action'] ?? 'keep');

            if ($action === 'replace') {
                $normalized[$fieldName] = isset($secretInput['value']) ? (string) $secretInput['value'] : '';
                continue;
            }

            if ($action === 'clear') {
                $normalized[$fieldName] = '';
            }
        }

        $webhookSecretAction = (string) ($input['webhookSecretAction'] ?? 'keep');

        if ($webhookSecretAction === 'replace') {
            $normalized['webhook_secret'] = isset($input['webhookSecret']) ? (string) $input['webhookSecret'] : '';
        }

        if ($webhookSecretAction === 'clear') {
            $normalized['webhook_secret'] = '';
        }

        return $normalized;
    }

    /**
     * @since 0.1.0
     */
    private function resolveConnection(WP_REST_Request $request): Connection|WP_Error
    {
        $connectionId = (int) $request->get_param('connection_id');

        if ($connectionId <= 0) {
            return new WP_Error('jooosi_mail_invalid_connection_id', 'A valid connection id is required.', ['status' => 400]);
        }

        $connection = $this->connectionRepository->find($connectionId);

        if (! $connection instanceof Connection) {
            return new WP_Error('jooosi_mail_connection_not_found', 'Connection not found.', ['status' => 404]);
        }

        return $connection;
    }

    /**
     * @param list<string> $path
     *
     * @since 0.1.0
     */
    private function extractConnectionSetting(Connection $connection, array $path, string $legacyKey): int|string|null
    {
        $value = $connection->settings;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                $value = $connection->settings[$legacyKey] ?? null;
                break;
            }

            $value = $value[$segment];
        }

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : (string) $value;
    }

    /**
     * @return array{email: string, name: string, forceEmail: bool, forceName: bool, returnPathMode: string, returnPathEmail: string}
     *
     * @since 0.1.0
     */
    private function extractSenderSettings(Connection $connection): array
    {
        $sender = is_array($connection->settings['sender'] ?? null) ? $connection->settings['sender'] : [];

        return [
            'email' => $this->extractSenderString($sender['email'] ?? null),
            'name' => $this->extractSenderString($sender['name'] ?? null),
            'forceEmail' => (bool) ($sender['force_email'] ?? false),
            'forceName' => (bool) ($sender['force_name'] ?? false),
            'returnPathMode' => $this->extractSenderString($sender['return_path_mode'] ?? null, 'inherit'),
            'returnPathEmail' => $this->extractSenderString($sender['return_path_email'] ?? null),
        ];
    }

    /**
     * @since 0.1.0
     */
    private function extractSenderString(mixed $value, string $default = ''): string
    {
        if (! is_scalar($value)) {
            return $default;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $default;
    }

    /**
     * @since 0.1.0
     */
    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return gmdate(DATE_ATOM, (int) $value);
        }

        return is_string($value) ? $value : null;
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Request;

use JooosiMail\Discovery\Attribute\Service;
use WP_Error;
use WP_REST_Request;
/**
 * Normalizes connection REST request bodies into application input.
 *
 * @since 1.0.9
 */
#[Service]
final class ConnectionRequestNormalizer
{
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function normalize(WP_REST_Request $request): array
    {
        $input = $this->input($request);
        $normalized = ['profile' => isset($input['profile']) ? (string) $input['profile'] : '', 'name' => isset($input['name']) ? (string) $input['name'] : '', 'dsn' => isset($input['dsn']) ? (string) $input['dsn'] : '', 'enabled' => (bool) ($input['enabled'] ?? \true), 'default' => (bool) ($input['default'] ?? \false), 'priority' => (int) ($input['priority'] ?? 10), 'weight' => (int) ($input['weight'] ?? 1), 'webhook_enabled' => (bool) ($input['webhookEnabled'] ?? \false)];
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
            $normalized['sender'] = ['email' => isset($sender['email']) ? (string) $sender['email'] : '', 'name' => isset($sender['name']) ? (string) $sender['name'] : '', 'force_email' => (bool) ($sender['forceEmail'] ?? \false), 'force_name' => (bool) ($sender['forceName'] ?? \false), 'return_path_mode' => isset($sender['returnPathMode']) ? (string) $sender['returnPathMode'] : 'inherit', 'return_path_email' => isset($sender['returnPathEmail']) ? (string) $sender['returnPathEmail'] : ''];
        }
        $configuration = is_array($input['configuration'] ?? null) ? $input['configuration'] : [];
        foreach ($configuration as $fieldName => $value) {
            if (is_string($fieldName)) {
                $normalized[$fieldName] = $value;
            }
        }
        $secretConfiguration = is_array($input['secretConfiguration'] ?? null) ? $input['secretConfiguration'] : [];
        foreach ($secretConfiguration as $fieldName => $secretInput) {
            if (!is_string($fieldName) || !is_array($secretInput)) {
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
     * @return bool|WP_Error
     *
     * @since 1.0.9
     */
    public function normalizeEnabled(WP_REST_Request $request): bool|WP_Error
    {
        $input = $this->input($request);
        if (!array_key_exists('enabled', $input)) {
            return new WP_Error('jooosi_mail_missing_enabled', 'The enabled field is required.', ['status' => 400]);
        }
        $enabled = filter_var($input['enabled'], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if ($enabled === null) {
            return new WP_Error('jooosi_mail_invalid_enabled', 'The enabled field must be a boolean.', ['status' => 400]);
        }
        return $enabled;
    }
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    private function input(WP_REST_Request $request): array
    {
        $body = $request->get_json_params();
        $input = is_array($body) ? $body : $request->get_params();
        return is_array($input) ? $input : [];
    }
}

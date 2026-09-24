<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Sender\SenderPolicyResolver;

/**
 * Resolves sender policy fields embedded in connection input.
 *
 * @since 1.0.9
 */
#[Service]
final class ConnectionSenderPolicyInputResolver
{
    public function __construct(
        private readonly ConnectionInputNormalizer $inputNormalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function resolve(array $input, array $settings): array
    {
        if (! array_key_exists('sender', $input)) {
            return $settings;
        }

        $sender = is_array($input['sender']) ? $input['sender'] : [];
        $senderSettings = [];
        $email = $this->inputNormalizer->extractScalarString($sender, 'email');
        $name = $this->inputNormalizer->extractScalarString($sender, 'name');
        $returnPathEmail = $this->inputNormalizer->extractScalarString($sender, 'return_path_email');
        $forceEmail = $this->inputNormalizer->resolveBoolean($sender, 'force_email', false);
        $forceName = $this->inputNormalizer->resolveBoolean($sender, 'force_name', false);
        $returnPathMode = $this->normalizeSenderMode(
            $sender['return_path_mode'] ?? SenderPolicyResolver::RETURN_PATH_MODE_INHERIT,
            [
                SenderPolicyResolver::RETURN_PATH_MODE_INHERIT,
                SenderPolicyResolver::RETURN_PATH_MODE_PROVIDER_DEFAULT,
                SenderPolicyResolver::RETURN_PATH_MODE_MATCH_FROM,
                SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM,
            ],
            SenderPolicyResolver::RETURN_PATH_MODE_INHERIT,
            'Return-Path',
        );

        if ($email !== null && $email !== '') {
            if (! is_email($email)) {
                throw new ConnectionConfigurationException('Sender email must be a valid email address.');
            }

            $senderSettings['email'] = $email;
        }

        if ($name !== null && $name !== '') {
            $senderSettings['name'] = sanitize_text_field($name);
        }

        if ($forceEmail) {
            $senderSettings['force_email'] = true;
        }

        if ($forceName) {
            $senderSettings['force_name'] = true;
        }

        if ($returnPathMode !== SenderPolicyResolver::RETURN_PATH_MODE_INHERIT) {
            $senderSettings['return_path_mode'] = $returnPathMode;
        }

        if ($returnPathEmail !== null && $returnPathEmail !== '') {
            if (! is_email($returnPathEmail)) {
                throw new ConnectionConfigurationException('Return-Path email must be a valid email address.');
            }

            $senderSettings['return_path_email'] = $returnPathEmail;
        }

        if ($returnPathMode === SenderPolicyResolver::RETURN_PATH_MODE_CUSTOM && ! isset($senderSettings['return_path_email'])) {
            throw new ConnectionConfigurationException('A custom Return-Path email address is required.');
        }

        if ($senderSettings === []) {
            unset($settings['sender']);
        } else {
            $settings['sender'] = $senderSettings;
        }

        return $settings;
    }

    /**
     * @param list<string> $allowedModes
     *
     * @since 1.0.9
     */
    private function normalizeSenderMode(mixed $value, array $allowedModes, string $defaultMode, string $label): string
    {
        if (! is_scalar($value)) {
            return $defaultMode;
        }

        $mode = strtolower(trim((string) $value));

        if (! in_array($mode, $allowedModes, true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConnectionConfigurationException(sprintf('%s mode is not supported.', $label));
        }

        return $mode;
    }
}

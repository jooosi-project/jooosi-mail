<?php

declare(strict_types=1);

namespace JooosiMail\Alert\Channel;

use JooosiMail\Alert\AlertMessage;

/**
 * Defines configuration validation and delivery for one alert provider.
 *
 * @since 1.0.12
 */
interface AlertChannelDriverInterface
{
    /**
     * Stable lowercase key used in settings and REST routes.
     *
     * @since 1.0.12
     */
    public function key(): string;

    /**
     * Human-readable provider name used in errors and responses.
     *
     * @since 1.0.12
     */
    public function displayName(): string;

    /**
     * Option field where the encrypted credential is stored.
     *
     * @since 1.0.12
     */
    public function secretOptionField(): string;

    /**
     * Option field where the destination is stored, or null when the provider has none.
     *
     * @since 1.0.12
     */
    public function targetOptionField(): ?string;

    /**
     * Validate a non-empty credential before it is encrypted.
     *
     * @since 1.0.12
     */
    public function validateSecret(string $secret): void;

    /**
     * Validate a non-empty target value when the provider requires one.
     *
     * @since 1.0.12
     */
    public function validateTarget(string $target): void;

    /**
     * Send one alert using this provider's API.
     *
     * @param array{enabled: bool, configured: bool, hasSavedCredential: bool, secret: string, target: string} $credentials
     *
     * @since 1.0.12
     */
    public function deliver(array $credentials, AlertMessage $message, bool $blocking): void;
}

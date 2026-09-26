<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Profile;

use JooosiMail\Mail\Connection\Connection;
/**
 * Contract for built-in and third-party mail profiles.
 *
 * @since 0.1.0
 */
interface MailProfileInterface
{
    /** @return list<string> */
    public function getSupportedSchemes(): array;
    /** @return array<string, mixed> */
    public function getConfigurationFields(): array;
    /** @return list<string> */
    public function getWebhookEvents(): array;
    public function supportsWebhooks(): bool;
    /** @return array<string, mixed> */
    public function getConfigurationDefaults(?Connection $existingConnection = null): array;
    public function validateConfiguration(Connection $connection): void;
    public function buildDsn(Connection $connection): ?string;
}

<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Connection;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Profile\MailProfileInterface;
use JooosiMail\Mail\Profile\ProfileMetadataResolver;
use JooosiMail\Mail\Profile\ProfileRegistry;
/**
 * Coordinates mail connection configuration persistence.
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionManager
{
    public function __construct(private readonly \JooosiMail\Mail\Connection\ConnectionRepository $connectionRepository, private readonly ProfileRegistry $profileRegistry, private readonly ProfileMetadataResolver $profileMetadataResolver, private readonly \JooosiMail\Mail\Connection\ConnectionInputResolver $connectionInputResolver, private readonly \JooosiMail\Mail\Connection\ConnectionConfigurationValidator $connectionConfigurationValidator)
    {
    }
    /**
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    public function listProfiles(): array
    {
        return array_map(function (MailProfileInterface $profile): array {
            $payload = ['key' => $this->profileMetadataResolver->getKey($profile), 'label' => $this->profileMetadataResolver->getLabel($profile), 'description' => $this->profileMetadataResolver->getDescription($profile), 'schemes' => $profile->getSupportedSchemes(), 'supports_webhooks' => $profile->supportsWebhooks(), 'configuration_fields' => $profile->getConfigurationFields()];
            $metadata = $this->profileMetadataResolver->resolve($profile);
            if ($metadata !== []) {
                $payload['metadata'] = $metadata;
            }
            return $payload;
        }, $this->profileRegistry->all());
    }
    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    public function create(array $input): \JooosiMail\Mail\Connection\Connection
    {
        $profile = $this->resolveProfile($input['profile'] ?? null);
        $connection = $this->resolveConnection(null, $profile, $input);
        $connectionId = $this->persist($connection);
        return $this->connectionRepository->find($connectionId) ?? throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException('The created connection could not be reloaded.');
    }
    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    public function update(int $connectionId, array $input): \JooosiMail\Mail\Connection\Connection
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        $existingConnection = $this->connectionRepository->find($connectionId) ?? throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Connection %d was not found.', $connectionId));
        $profileKey = $input['profile'] ?? $existingConnection->profileKey;
        $profile = $this->resolveProfile($profileKey);
        $connection = $this->resolveConnection($existingConnection, $profile, $input);
        $this->persist($connection);
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        return $this->connectionRepository->find($connectionId) ?? throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Connection %d could not be reloaded.', $connectionId));
    }
    /**
     * @since 0.1.0
     */
    public function setDefault(int $connectionId): \JooosiMail\Mail\Connection\Connection
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        $connection = $this->connectionRepository->find($connectionId) ?? throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Connection %d was not found.', $connectionId));
        $updatedConnection = new \JooosiMail\Mail\Connection\Connection(id: $connection->id, profileKey: $connection->profileKey, name: $connection->name, dsn: $connection->dsn, settings: $connection->settings, secrets: $connection->secrets, enabled: $connection->enabled, default: \true, priority: $connection->priority, weight: $connection->weight, webhookEnabled: $connection->webhookEnabled);
        $this->persist($updatedConnection);
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        return $this->connectionRepository->find($connectionId) ?? throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Connection %d could not be reloaded.', $connectionId));
    }
    /**
     * @since 0.1.0
     */
    public function setEnabled(int $connectionId, bool $enabled): \JooosiMail\Mail\Connection\Connection
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        $connection = $this->connectionRepository->find($connectionId) ?? throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Connection %d was not found.', $connectionId));
        $updatedConnection = new \JooosiMail\Mail\Connection\Connection(id: $connection->id, profileKey: $connection->profileKey, name: $connection->name, dsn: $connection->dsn, settings: $connection->settings, secrets: $connection->secrets, enabled: $enabled, default: $enabled ? $connection->default : \false, priority: $connection->priority, weight: $connection->weight, webhookEnabled: $connection->webhookEnabled);
        $this->persist($updatedConnection);
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        return $this->connectionRepository->find($connectionId) ?? throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Connection %d could not be reloaded.', $connectionId));
    }
    /**
     * @since 0.1.0
     */
    public function delete(int $connectionId): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        $connection = $this->connectionRepository->find($connectionId) ?? throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Connection %d was not found.', $connectionId));
        $this->connectionRepository->delete($connectionId);
        $this->assignFallbackDefaultIfNeeded();
    }
    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function resolveConnection(?\JooosiMail\Mail\Connection\Connection $existingConnection, MailProfileInterface $profile, array $input): \JooosiMail\Mail\Connection\Connection
    {
        $connection = $this->connectionInputResolver->resolve($existingConnection, $profile, $input);
        $this->connectionConfigurationValidator->validate($profile, $connection);
        return $connection;
    }
    /**
     * @since 0.1.0
     */
    private function persist(\JooosiMail\Mail\Connection\Connection $connection): int
    {
        if ($connection->default) {
            $this->connectionRepository->clearDefault($connection->id);
        }
        $connectionId = $this->connectionRepository->save($connection);
        $this->assignFallbackDefaultIfNeeded();
        return $connectionId;
    }
    /**
     * @since 0.1.0
     */
    private function assignFallbackDefaultIfNeeded(): void
    {
        if ($this->connectionRepository->findDefault() instanceof \JooosiMail\Mail\Connection\Connection) {
            return;
        }
        $activeConnections = $this->connectionRepository->findActive();
        $nextDefault = $activeConnections === [] ? null : $activeConnections[array_key_first($activeConnections)];
        if (!$nextDefault instanceof \JooosiMail\Mail\Connection\Connection || $nextDefault->id === null) {
            return;
        }
        $this->connectionRepository->clearDefault($nextDefault->id);
        $this->connectionRepository->save(new \JooosiMail\Mail\Connection\Connection(id: $nextDefault->id, profileKey: $nextDefault->profileKey, name: $nextDefault->name, dsn: $nextDefault->dsn, settings: $nextDefault->settings, secrets: $nextDefault->secrets, enabled: $nextDefault->enabled, default: \true, priority: $nextDefault->priority, weight: $nextDefault->weight, webhookEnabled: $nextDefault->webhookEnabled));
    }
    /**
     * @since 0.1.0
     */
    private function resolveProfile(mixed $profileKey): MailProfileInterface
    {
        if (!is_scalar($profileKey) || (string) $profileKey === '') {
            throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException('A connection profile key is required.');
        }
        $profile = $this->profileRegistry->get((string) $profileKey);
        if (!$profile instanceof MailProfileInterface) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \JooosiMail\Mail\Connection\ConnectionConfigurationException(sprintf('Profile "%s" is not registered.', (string) $profileKey));
        }
        return $profile;
    }
}

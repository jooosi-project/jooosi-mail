<?php

declare(strict_types=1);

namespace JooosiMail\Cli\Application;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionManager;
use JooosiMail\Mail\Connection\ConnectionRepository;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;

/**
 * Coordinates connection operations used by the WP-CLI adapter.
 *
 * This service deliberately delegates persistence and routing decisions to the
 * existing mail-layer services. It owns only the application-level operation
 * boundary needed by the command, so command output and error handling remain
 * at the WP-CLI edge.
 *
 * @since 1.0.9
 */
#[Service]
final class ConnectionCommandApplicationService
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly ConnectionRepository $connectionRepository,
        private readonly ConnectionStatusReporter $connectionStatusReporter,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 1.0.9
     */
    public function create(array $input): Connection
    {
        return $this->connectionManager->create($input);
    }

    /**
     * @since 1.0.9
     */
    public function find(int $connectionId): ?Connection
    {
        return $this->connectionRepository->find($connectionId);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 1.0.9
     */
    public function update(int $connectionId, array $input): Connection
    {
        return $this->connectionManager->update($connectionId, $input);
    }

    /**
     * @since 1.0.9
     */
    public function setEnabled(int $connectionId, bool $enabled): Connection
    {
        return $this->connectionManager->setEnabled($connectionId, $enabled);
    }

    /**
     * @since 1.0.9
     */
    public function delete(int $connectionId): void
    {
        $this->connectionManager->delete($connectionId);
    }

    /**
     * @return list<Connection>
     *
     * @since 1.0.9
     */
    public function listConnections(): array
    {
        return $this->connectionRepository->findAll();
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function listProfiles(): array
    {
        return $this->connectionManager->listProfiles();
    }

    /**
     * @since 1.0.9
     */
    public function setDefault(int $connectionId): Connection
    {
        return $this->connectionManager->setDefault($connectionId);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @since 1.0.9
     */
    public function getStatuses(bool $includeDisabled = false): array
    {
        return $this->connectionStatusReporter->getStatuses($includeDisabled);
    }

    /**
     * @return array<string, int|null>
     *
     * @since 1.0.9
     */
    public function summarizeActiveConnections(): array
    {
        return $this->connectionStatusReporter->summarizeActiveConnections();
    }
}

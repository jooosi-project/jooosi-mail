<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Application;

use JooosiMail\Admin\Connection\AdminConnectionPresenter;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionManager;
use JooosiMail\Mail\Connection\ConnectionRepository;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;
/**
 * Coordinates admin connection use cases without owning REST concerns.
 *
 * @since 1.0.9
 */
#[Service]
final class ConnectionApplicationService
{
    public function __construct(private readonly ConnectionRepository $connectionRepository, private readonly ConnectionManager $connectionManager, private readonly ConnectionStatusReporter $connectionStatusReporter, private readonly AdminConnectionPresenter $presenter)
    {
    }
    /**
     * @return array{profiles: list<array<string, mixed>>, connections: list<array<string, mixed>>}
     *
     * @since 1.0.9
     */
    public function listPayload(): array
    {
        $statusMap = $this->getStatusMap();
        $connections = [];
        foreach ($this->connectionRepository->findAll() as $connection) {
            $connections[] = $this->presenter->listItem($connection, $statusMap[$connection->id ?? 0] ?? null);
        }
        return ['profiles' => $this->presenter->profiles($this->connectionManager->listProfiles()), 'connections' => $connections];
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
    public function create(array $input): Connection
    {
        return $this->connectionManager->create($input);
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
    public function delete(int $connectionId): void
    {
        $this->connectionManager->delete($connectionId);
    }
    /**
     * @since 1.0.9
     */
    public function makeDefault(int $connectionId): Connection
    {
        return $this->connectionManager->setDefault($connectionId);
    }
    /**
     * @since 1.0.9
     */
    public function setEnabled(int $connectionId, bool $enabled): Connection
    {
        return $this->connectionManager->setEnabled($connectionId, $enabled);
    }
    /**
     * @return array<string, mixed>
     *
     * @since 1.0.9
     */
    public function detailPayload(Connection $connection): array
    {
        return $this->presenter->detail($connection, $this->getStatusMap()[$connection->id ?? 0] ?? null);
    }
    /**
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.9
     */
    private function getStatusMap(): array
    {
        $map = [];
        foreach ($this->connectionStatusReporter->getStatuses(\true) as $status) {
            $connection = $status['connection'];
            if ($connection->id === null) {
                continue;
            }
            $map[$connection->id] = $status;
        }
        return $map;
    }
}

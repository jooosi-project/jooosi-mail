<?php

declare(strict_types=1);

namespace JooosiMail\Cli;

use JooosiMail\Cli\Application\CliBooleanParser;
use JooosiMail\Cli\Application\CliInputNormalizer;
use JooosiMail\Cli\Application\ConnectionCommandApplicationService;
use JooosiMail\Cli\Presentation\CliValuePresenter;
use JooosiMail\Cli\Presentation\ConnectionCliPresenter;
use JooosiMail\Discovery\Attribute\Command;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\ConnectionConfigurationException;
use JooosiMail\Mail\Connection\ConnectionManager;
use JooosiMail\Mail\Connection\ConnectionRepository;
use JooosiMail\Mail\Routing\ConnectionStatusReporter;
use WP_CLI;

use function WP_CLI\Utils\format_items;

/**
 * Manage connections.
 *
 * ## EXAMPLES
 *
 *     # Create an SMTP connection.
 *     $ wp jooosi-mail connection:create --profile=smtp --name="Primary SMTP" --host=smtp.example.com --port=587 --username=user --password=secret
 *     Success: Created connection #1 (Primary SMTP).
 *
 *     # Inspect configured connections.
 *     $ wp jooosi-mail connection:list
 *     id  name          profile  enabled  default  priority  weight  webhooks
 *     1   Primary SMTP  smtp     yes      yes      10        1       no
 *
 * @since 0.1.0
 */
#[Service]
final class ConnectionCommand
{
    private readonly ConnectionCommandApplicationService $applicationService;

    private readonly CliInputNormalizer $inputNormalizer;

    private readonly ConnectionCliPresenter $presenter;

    public function __construct(
        ConnectionManager $connectionManager,
        ConnectionRepository $connectionRepository,
        ConnectionStatusReporter $connectionStatusReporter,
        ?ConnectionCommandApplicationService $applicationService = null,
        ?CliInputNormalizer $inputNormalizer = null,
        ?ConnectionCliPresenter $presenter = null,
    ) {
        $this->applicationService = $applicationService ?? new ConnectionCommandApplicationService(
            $connectionManager,
            $connectionRepository,
            $connectionStatusReporter,
        );
        $this->inputNormalizer = $inputNormalizer ?? new CliInputNormalizer(new CliBooleanParser());
        $this->presenter = $presenter ?? new ConnectionCliPresenter(new CliValuePresenter());
    }

    /**
     * Create a Jooosi Mail connection.
     *
     * ## OPTIONS
     *
     * --profile=<profile>
     * : Profile key to configure.
     *
     * --name=<name>
     * : Human-friendly name for the connection.
     *
     * [--dsn=<dsn>]
     * : Optional raw transport override. Most connections should rely on profile fields.
     *
     * [--enabled=<enabled>]
     * : Whether the connection starts enabled.
     * ---
     * default: true
     * options:
     *   - 0
     *   - 1
     *   - false
     *   - true
     * ---
     *
     * [--default=<default>]
     * : Whether the connection becomes the default route.
     * ---
     * default: false
     * options:
     *   - 0
     *   - 1
     *   - false
     *   - true
     * ---
     *
     * [--priority=<priority>]
     * : Routing priority. Higher values are preferred.
     * ---
     * default: 10
     * ---
     *
     * [--weight=<weight>]
     * : Weighted round-robin weight.
     * ---
     * default: 1
     * ---
     *
     * [--webhook-enabled=<webhook-enabled>]
     * : Whether webhook ingestion is enabled for the connection.
     * ---
     * default: false
     * options:
     *   - 0
     *   - 1
     *   - false
     *   - true
     * ---
     *
      * [--webhook-secret=<webhook-secret>]
      * : Shared secret used to validate incoming webhook requests.
     *
     * [--settings-json=<settings-json>]
     * : JSON object merged into the connection settings.
     *
     * [--rate-limit-minute=<rate-limit-minute>]
     * : Per-minute delivery limit. Use `0` to disable the limit.
     *
     * [--rate-limit-hour=<rate-limit-hour>]
     * : Per-hour delivery limit. Use `0` to disable the limit.
     *
     * [--rate-limit-day=<rate-limit-day>]
     * : Per-day delivery limit. Use `0` to disable the limit.
     *
     * [--circuit-threshold=<circuit-threshold>]
     * : Failure count that opens the circuit breaker. Use `0` to disable it.
     *
     * [--circuit-window=<circuit-window>]
     * : Rolling failure window in seconds. Use `0` to disable it.
     *
     * [--circuit-cooldown=<circuit-cooldown>]
     * : Cooldown period in seconds after the circuit opens. Use `0` to disable it.
     *
     * [--<field>=<value>]
     * : Profile-specific fields, such as `--host`, `--port`, `--username`, `--password`, `--smtp-credential`, `--scheme`, `--api-key`, `--api-version`, `--resource-name`, `--disable-tracking`, `--client-id`, `--client-secret`, `--workspace-id`, `--channel`, `--domain`, `--account-type`, `--access-key`, `--secret-key`, `--project-id`, `--token`, `--api-token`, `--inbox-id`, `--sandbox`, `--region`, or `--command`.
     *
     * ## EXAMPLES
     *
     *     # Create an SMTP connection from discrete fields.
     *     $ wp jooosi-mail connection:create --profile=smtp --name="Primary SMTP" --host=smtp.example.com --port=587 --username=user --password=secret
     *     Success: Created connection #1 (Primary SMTP).
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Create a Jooosi Mail connection.')]
    public function create(array $args, array $assocArgs): void
    {
        try {
            $connection = $this->applicationService->create($assocArgs);

            WP_CLI::success(sprintf('Created connection #%d (%s).', $connection->id, $connection->name));
        } catch (ConnectionConfigurationException $exception) {
            WP_CLI::error($exception->getMessage());
        }
    }

    /**
     * Update a Jooosi Mail connection.
     *
     * ## OPTIONS
     *
     * <connection-id>
     * : Connection id to update.
     *
     * [--profile=<profile>]
     * : Replace the current profile key.
     *
     * [--name=<name>]
     * : Replace the connection name.
     *
      * [--dsn=<dsn>]
      * : Replace the optional raw transport override. Pass an empty value (`--dsn=`) to clear it.
      *
      * [--webhook-secret=<webhook-secret>]
      * : Replace the shared webhook secret. Pass an empty value (`--webhook-secret=`) to clear it.
      *
       * [--<field>=<value>]
      * : Any create-time field, including `--enabled`, `--default`, `--priority`, `--weight`, `--webhook-enabled`, `--settings-json`, `--rate-limit-hour`, `--circuit-threshold`, `--host`, `--port`, `--username`, `--password`, `--smtp-credential`, `--scheme`, `--api-key`, `--api-version`, `--resource-name`, `--disable-tracking`, `--client-id`, `--client-secret`, `--workspace-id`, `--channel`, `--domain`, `--account-type`, `--access-key`, `--secret-key`, `--project-id`, `--token`, `--api-token`, `--inbox-id`, `--sandbox`, `--region`, or `--command`.
     *
     * ## EXAMPLES
     *
     *     # Raise the weight and hourly rate limit for a connection.
     *     $ wp jooosi-mail connection:update 3 --weight=5 --priority=10 --rate-limit-hour=500
     *     Success: Updated connection #3 (Primary SMTP).
     *
     *     # Rotate SMTP credentials and the webhook secret.
     *     $ wp jooosi-mail connection:update 3 --username=mailer --password=new-secret --webhook-secret=shared-secret
     *     Success: Updated connection #3 (Primary SMTP).
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Update a Jooosi Mail connection.')]
    public function update(array $args, array $assocArgs): void
    {
        $connectionId = $this->resolveConnectionId($args);
        $existingConnection = $this->applicationService->find($connectionId);

        if ($existingConnection === null) {
            WP_CLI::error(sprintf('Connection %d was not found.', $connectionId));
        }

        try {
            $connection = $this->applicationService->update($connectionId, $assocArgs);

            WP_CLI::success(sprintf('Updated connection #%d (%s).', $connection->id, $connection->name));
        } catch (ConnectionConfigurationException $exception) {
            WP_CLI::error($exception->getMessage());
        }
    }

    /**
     * Enable a Jooosi Mail connection.
     *
     * ## OPTIONS
     *
     * <connection-id>
     * : Connection id to enable.
     *
     * ## EXAMPLES
     *
     *     # Enable a connection.
     *     $ wp jooosi-mail connection:enable 3
     *     Success: Enabled connection #3 (Backup SMTP).
     *
     *     # Re-enable a previously disabled route.
     *     $ wp jooosi-mail connection:enable 7
     *     Success: Enabled connection #7 (Transactional SMTP).
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Enable a Jooosi Mail connection.')]
    public function enable(array $args, array $assocArgs): void
    {
        $connectionId = $this->resolveConnectionId($args);

        try {
            $connection = $this->applicationService->setEnabled($connectionId, true);

            WP_CLI::success(sprintf('Enabled connection #%d (%s).', $connection->id, $connection->name));
        } catch (ConnectionConfigurationException $exception) {
            WP_CLI::error($exception->getMessage());
        }
    }

    /**
     * Disable a Jooosi Mail connection.
     *
     * ## OPTIONS
     *
     * <connection-id>
     * : Connection id to disable.
     *
     * ## EXAMPLES
     *
     *     # Disable a connection.
     *     $ wp jooosi-mail connection:disable 3
     *     Success: Disabled connection #3 (Backup SMTP).
     *
     *     # Take a connection out of rotation.
     *     $ wp jooosi-mail connection:disable 7
     *     Success: Disabled connection #7 (Transactional SMTP).
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Disable a Jooosi Mail connection.')]
    public function disable(array $args, array $assocArgs): void
    {
        $connectionId = $this->resolveConnectionId($args);

        try {
            $connection = $this->applicationService->setEnabled($connectionId, false);

            WP_CLI::success(sprintf('Disabled connection #%d (%s).', $connection->id, $connection->name));
        } catch (ConnectionConfigurationException $exception) {
            WP_CLI::error($exception->getMessage());
        }
    }

    /**
     * Delete a Jooosi Mail connection.
     *
     * ## OPTIONS
     *
     * <connection-id>
     * : Connection id to delete.
     *
     * [--yes]
     * : Answer yes to the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     # Delete a connection interactively.
     *     $ wp jooosi-mail connection:delete 3
     *     Delete connection 3? [y/n] y
     *     Success: Deleted connection #3.
     *
     *     # Skip the confirmation prompt.
     *     $ wp jooosi-mail connection:delete 3 --yes
     *     Success: Deleted connection #3.
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Delete a Jooosi Mail connection.')]
    public function delete(array $args, array $assocArgs): void
    {
        $connectionId = $this->resolveConnectionId($args);

        WP_CLI::confirm(sprintf('Delete connection %d?', $connectionId), $assocArgs);

        try {
            $this->applicationService->delete($connectionId);

            WP_CLI::success(sprintf('Deleted connection #%d.', $connectionId));
        } catch (ConnectionConfigurationException $exception) {
            WP_CLI::error($exception->getMessage());
        }
    }

    /**
     * List configured Jooosi Mail connections.
     *
     * ## EXAMPLES
     *
     *     # List configured connections.
     *     $ wp jooosi-mail connection:list
     *     id  name          profile  enabled  default  priority  weight  webhooks
     *     1   Primary SMTP  smtp     yes      yes      10        1       no
     *
     *     # Show that no connections are configured yet.
     *     $ wp jooosi-mail connection:list
     *     No connections configured.
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'List configured Jooosi Mail connections.')]
    public function list(array $args, array $assocArgs): void
    {
        $items = array_map(
            fn ($connection): array => $this->presenter->connection($connection),
            $this->applicationService->listConnections(),
        );

        if ($items === []) {
            WP_CLI::line('No connections configured.');

            return;
        }

        format_items('table', $items, ['id', 'name', 'profile', 'enabled', 'default', 'priority', 'weight', 'webhooks']);
    }

    /**
     * List available Jooosi Mail connection profiles.
     *
     * ## EXAMPLES
     *
     *     # Show built-in connection profiles.
     *     $ wp jooosi-mail connection:profiles
     *     key       label     schemes     webhooks  fields
     *     smtp      SMTP      smtp,smtps  no        scheme,host,port,username,password
     *     sendmail  Sendmail  sendmail    no        command
     *     ...
     *
     *     # Review profile field names before creating a connection.
     *     $ wp jooosi-mail connection:profiles
     *     key    label  schemes     webhooks  fields
     *     smtp   SMTP   smtp,smtps  no        scheme,host,port,username,password
     *     ...
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'List available Jooosi Mail connection profiles.')]
    public function profiles(array $args, array $assocArgs): void
    {
        $items = array_map(
            fn (array $profile): array => $this->presenter->profile($profile),
            $this->applicationService->listProfiles(),
        );

        format_items('table', $items, ['key', 'label', 'schemes', 'webhooks', 'fields']);
    }

    /**
     * Set a Jooosi Mail connection as default.
     *
     * ## OPTIONS
     *
     * <connection-id>
     * : Connection id to mark as the default route.
     *
     * ## EXAMPLES
     *
     *     # Mark a connection as the default route.
     *     $ wp jooosi-mail connection:set-default 3
     *     Success: Connection #3 (Primary SMTP) is now the default.
     *
     *     # Promote a backup route after maintenance.
     *     $ wp jooosi-mail connection:set-default 7
     *     Success: Connection #7 (Backup SMTP) is now the default.
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Set a Jooosi Mail connection as default.')]
    public function setDefault(array $args, array $assocArgs): void
    {
        $connectionId = $this->resolveConnectionId($args);

        try {
            $connection = $this->applicationService->setDefault($connectionId);

            WP_CLI::success(sprintf('Connection #%d (%s) is now the default.', $connection->id, $connection->name));
        } catch (ConnectionConfigurationException $exception) {
            WP_CLI::error($exception->getMessage());
        }
    }

    /**
     * Show Jooosi Mail connection routing status.
     *
     * ## OPTIONS
     *
     * [--all]
     * : Include disabled connections in the report.
     *
     * ## EXAMPLES
     *
     *     # Show routing health for active connections.
     *     $ wp jooosi-mail connection:status
     *     id  name          profile  enabled  default  health  available  reasons  blacklisted_until  next_available_at  rate_limits
     *     1   Primary SMTP  smtp     yes      yes      95      yes        -        -                  -                  hour:500/500
     *     Active: 1
     *     Available: 1
     *     Temporarily unavailable: 0
     *
     *     # Include disabled connections in the report.
     *     $ wp jooosi-mail connection:status --all
     *     id  name          profile  enabled  default  health  available  reasons   blacklisted_until  next_available_at  rate_limits
     *     2   Backup SMTP   smtp     no       no       0       no         disabled  -                  -                  -
     *     Active: 1
     *     Available: 1
     *     Temporarily unavailable: 0
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     *
     * @since 0.1.0
     */
    #[Command(description: 'Show Jooosi Mail connection routing status.')]
    public function status(array $args, array $assocArgs): void
    {
        $includeDisabled = $this->inputNormalizer->booleanOption($assocArgs, 'all');
        $statuses = $this->applicationService->getStatuses($includeDisabled);

        if ($statuses === []) {
            WP_CLI::line('No connections found.');

            return;
        }

        $items = array_map(
            fn (array $status): array => $this->presenter->status($status),
            $statuses,
        );

        format_items('table', $items, [
            'id',
            'name',
            'profile',
            'enabled',
            'default',
            'health',
            'available',
            'reasons',
            'blacklisted_until',
            'next_available_at',
            'rate_limits',
        ]);

        $summary = $this->applicationService->summarizeActiveConnections();
        WP_CLI::line(sprintf('Active: %d', (int) ($summary['active_connections'] ?? 0)));
        WP_CLI::line(sprintf('Available: %d', (int) ($summary['available_connections'] ?? 0)));
        WP_CLI::line(sprintf('Temporarily unavailable: %d', (int) ($summary['temporarily_unavailable_connections'] ?? 0)));

        if (($summary['next_available_at'] ?? null) !== null) {
            WP_CLI::line(sprintf('Next available at: %s', $this->presenter->timestamp($summary['next_available_at'])));
        }
    }

    /**
     * @param array<int, string> $args
     *
     * @since 0.1.0
     */
    private function resolveConnectionId(array $args): int
    {
        $connectionId = $this->inputNormalizer->firstIntegerArgument($args);

        if ($connectionId <= 0) {
            WP_CLI::error('Provide the connection id as the first argument.');
        }

        return $connectionId;
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Cli;

use JooosiMail\Cli\Application\ConnectionCommandApplicationService;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use RuntimeException;

/**
 * Covers connection WP-CLI command behavior.
 *
 * @since 0.1.0
 */
final class ConnectionCommandTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testCreateListProfilesAndDeleteManageConnectionsThroughCli(): void
    {
        $create = $this->captureCli(function (): void {
            $this->connectionCommand()->create([], [
                'profile' => 'null',
                'name' => 'CLI Primary Connection',
                'default' => true,
                'priority' => 10,
                'weight' => 1,
            ]);
        });
        $connection = $this->connectionRepository()->findDefault();

        self::assertNotNull($connection);
        self::assertStringContainsString('Success: Created connection #', $create['stdout']);
        self::assertSame('CLI Primary Connection', $connection->name);

        $list = $this->captureCli(function (): void {
            $this->connectionCommand()->list([], []);
        });
        $profiles = $this->captureCli(function (): void {
            $this->connectionCommand()->profiles([], []);
        });

        self::assertStringContainsString('CLI Primary Connection', $list['stdout']);
        self::assertStringContainsString('null', $list['stdout']);
        self::assertStringContainsString('smtp', $profiles['stdout']);
        self::assertStringContainsString('null', $profiles['stdout']);
        self::assertStringContainsString('sendgrid', $profiles['stdout']);
        self::assertStringContainsString('ahasend', $profiles['stdout']);
        self::assertStringContainsString('bird', $profiles['stdout']);
        self::assertStringContainsString('brevo', $profiles['stdout']);
        self::assertStringContainsString('cloudflare', $profiles['stdout']);
        self::assertStringContainsString('azure', $profiles['stdout']);
        self::assertStringContainsString('elasticemail', $profiles['stdout']);
        self::assertStringContainsString('emailit', $profiles['stdout']);
        self::assertStringContainsString('gmail', $profiles['stdout']);
        self::assertStringContainsString('zeptomail', $profiles['stdout']);
        self::assertStringContainsString('mailgun', $profiles['stdout']);
        self::assertStringContainsString('infobip', $profiles['stdout']);
        self::assertStringContainsString('mailtrap', $profiles['stdout']);
        self::assertStringContainsString('mailersend', $profiles['stdout']);
        self::assertStringContainsString('mailjet', $profiles['stdout']);
        self::assertStringContainsString('mailomat', $profiles['stdout']);
        self::assertStringContainsString('mandrill', $profiles['stdout']);
        self::assertStringContainsString('mailpace', $profiles['stdout']);
        self::assertStringContainsString('microsoftgraph', $profiles['stdout']);
        self::assertStringContainsString('pepipost', $profiles['stdout']);
        self::assertStringContainsString('postal', $profiles['stdout']);
        self::assertStringContainsString('postmark', $profiles['stdout']);
        self::assertStringContainsString('resend', $profiles['stdout']);
        self::assertStringContainsString('scaleway', $profiles['stdout']);
        self::assertStringContainsString('sendlayer', $profiles['stdout']);
        self::assertStringContainsString('sendpulse', $profiles['stdout']);
        self::assertStringContainsString('ses', $profiles['stdout']);
        self::assertStringContainsString('smtp2go', $profiles['stdout']);
        self::assertStringContainsString('smtpcom', $profiles['stdout']);
        self::assertStringContainsString('sparkpost', $profiles['stdout']);
        self::assertStringContainsString('sweego', $profiles['stdout']);
        self::assertStringContainsString('zohomail', $profiles['stdout']);

        $delete = $this->captureCli(function () use ($connection): void {
            $this->connectionCommand()->delete([(string) $connection->id], ['yes' => true]);
        });

        self::assertStringContainsString(sprintf('Success: Deleted connection #%d.', $connection->id), $delete['stdout']);
        self::assertSame([], $this->connectionRepository()->findAll());
    }

    /**
     * @since 0.1.0
     */
    public function testSetDefaultAndStatusReflectUnavailableConnections(): void
    {
        $primaryConnection = $this->createNullConnection([
            'name' => 'CLI Primary Route',
            'default' => true,
        ]);
        $backupConnection = $this->createNullConnection([
            'name' => 'CLI Backup Route',
            'default' => false,
            'priority' => 5,
            'circuit_threshold' => 1,
            'circuit_window' => 300,
            'circuit_cooldown' => 300,
        ]);

        $setDefault = $this->captureCli(function () use ($backupConnection): void {
            $this->connectionCommand()->setDefault([(string) $backupConnection->id], []);
        });

        self::assertStringContainsString(
            sprintf('Success: Connection #%d (%s) is now the default.', $backupConnection->id, $backupConnection->name),
            $setDefault['stdout'],
        );
        self::assertSame($backupConnection->id, $this->connectionRepository()->findDefault()?->id);

        $this->circuitBreaker()->recordFailure($backupConnection, new RuntimeException('CLI status failure.'));

        $status = $this->captureCli(function (): void {
            $this->connectionCommand()->status([], []);
        });

        self::assertStringContainsString('CLI Backup Route', $status['stdout']);
        self::assertStringContainsString('circuit_breaker', $status['stdout']);
        self::assertStringContainsString('Active: 2', $status['stdout']);
        self::assertStringContainsString('Available: 1', $status['stdout']);
        self::assertStringContainsString('Temporarily unavailable: 1', $status['stdout']);
        self::assertStringContainsString('CLI Primary Route', $status['stdout']);
        self::assertNotSame($primaryConnection->id, $this->connectionRepository()->findDefault()?->id);
    }

    /**
     * @since 1.0.9
     */
    public function testStatusAllUsesRobustBooleanOptionParsing(): void
    {
        $activeConnection = $this->createNullConnection([
            'name' => 'CLI Active Route',
            'default' => true,
        ]);
        $disabledConnection = $this->createNullConnection([
            'name' => 'CLI Disabled Route',
            'default' => false,
            'enabled' => false,
        ]);

        $withoutDisabled = $this->captureCli(function (): void {
            $this->connectionCommand()->status([], ['all' => 'false']);
        });

        self::assertStringContainsString($activeConnection->name, $withoutDisabled['stdout']);
        self::assertStringNotContainsString($disabledConnection->name, $withoutDisabled['stdout']);

        $withDisabled = $this->captureCli(function (): void {
            $this->connectionCommand()->status([], ['all' => 'true']);
        });

        self::assertStringContainsString($activeConnection->name, $withDisabled['stdout']);
        self::assertStringContainsString($disabledConnection->name, $withDisabled['stdout']);
        self::assertStringContainsString('disabled', strtolower($withDisabled['stdout']));
    }

    /**
     * @since 1.0.9
     */
    public function testConnectionApplicationServiceCoordinatesOperationsWithoutCliOutput(): void
    {
        $service = $this->container()->get(ConnectionCommandApplicationService::class);
        self::assertInstanceOf(ConnectionCommandApplicationService::class, $service);

        $connection = $service->create([
            'profile' => 'null',
            'name' => 'CLI Application Connection',
            'default' => true,
        ]);

        self::assertNotNull($connection->id);
        self::assertSame($connection->id, $service->find($connection->id)?->id);
        self::assertCount(1, $service->listConnections());
        self::assertNotEmpty($service->listProfiles());
        self::assertNotEmpty($service->getStatuses());

        $updated = $service->setEnabled($connection->id, false);

        self::assertFalse($updated->enabled);
        self::assertSame([], $service->getStatuses());
    }

    /**
     * @since 0.1.0
     */
    public function testUpdateEnableAndDisableChangePersistedConnectionState(): void
    {
        $primaryConnection = $this->createNullConnection([
            'name' => 'CLI Default Route',
            'default' => true,
        ]);
        $connection = $this->createNullConnection([
            'name' => 'CLI Mutable Connection',
            'default' => false,
            'priority' => 3,
            'weight' => 2,
        ]);

        $update = $this->captureCli(function () use ($connection): void {
            $this->connectionCommand()->update([(string) $connection->id], [
                'name' => 'CLI Updated Connection',
                'priority' => 25,
                'weight' => 7,
            ]);
        });
        $updatedConnection = $this->connectionRepository()->find($connection->id ?? 0);

        self::assertStringContainsString(
            sprintf('Success: Updated connection #%d (CLI Updated Connection).', $connection->id),
            $update['stdout'],
        );
        self::assertNotNull($updatedConnection);
        self::assertSame('CLI Updated Connection', $updatedConnection->name);
        self::assertSame(25, $updatedConnection->priority);
        self::assertSame(7, $updatedConnection->weight);

        $disable = $this->captureCli(function () use ($connection): void {
            $this->connectionCommand()->disable([(string) $connection->id], []);
        });
        $disabledConnection = $this->connectionRepository()->find($connection->id ?? 0);

        self::assertStringContainsString(
            sprintf('Success: Disabled connection #%d (CLI Updated Connection).', $connection->id),
            $disable['stdout'],
        );
        self::assertNotNull($disabledConnection);
        self::assertFalse($disabledConnection->enabled);
        self::assertFalse($disabledConnection->default);
        self::assertSame($primaryConnection->id, $this->connectionRepository()->findDefault()?->id);

        $enable = $this->captureCli(function () use ($connection): void {
            $this->connectionCommand()->enable([(string) $connection->id], []);
        });
        $enabledConnection = $this->connectionRepository()->find($connection->id ?? 0);

        self::assertStringContainsString(
            sprintf('Success: Enabled connection #%d (CLI Updated Connection).', $connection->id),
            $enable['stdout'],
        );
        self::assertNotNull($enabledConnection);
        self::assertTrue($enabledConnection->enabled);
        self::assertFalse($enabledConnection->default);
    }
}

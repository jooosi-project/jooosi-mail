<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Connection;

use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Covers connection manager updates that change only the requested state.
 *
 * @since 1.0.9
 */
final class ConnectionManagerImmutableUpdateTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function testStateUpdatesPreserveUnrelatedConnectionFields(): void
    {
        $connection = $this->saveConnection(new Connection(
            id: null,
            profileKey: 'null',
            name: 'Preserved Null Connection',
            dsn: 'null://null',
            settings: [
                'profile' => ['scheme' => 'null'],
                'rate_limits' => ['minute' => 10],
                'sender' => ['email' => 'sender@example.test'],
            ],
            secrets: [
                'profile' => ['token' => 'secret'],
                'webhook_secret' => 'webhook-secret',
            ],
            enabled: true,
            default: false,
            priority: 4,
            weight: 8,
            webhookEnabled: true,
        ));

        $setDefault = $this->connectionManager()->setDefault($connection->id ?? 0);

        self::assertSame($connection->id, $setDefault->id);
        self::assertSame($connection->profileKey, $setDefault->profileKey);
        self::assertSame($connection->name, $setDefault->name);
        self::assertSame($connection->dsn, $setDefault->dsn);
        self::assertSame($connection->settings, $setDefault->settings);
        self::assertSame($connection->secrets, $setDefault->secrets);
        self::assertSame($connection->enabled, $setDefault->enabled);
        self::assertTrue($setDefault->default);
        self::assertSame($connection->priority, $setDefault->priority);
        self::assertSame($connection->weight, $setDefault->weight);
        self::assertSame($connection->webhookEnabled, $setDefault->webhookEnabled);

        $disabled = $this->connectionManager()->setEnabled($connection->id ?? 0, false);

        self::assertSame($connection->id, $disabled->id);
        self::assertSame($connection->profileKey, $disabled->profileKey);
        self::assertSame($connection->name, $disabled->name);
        self::assertSame($connection->dsn, $disabled->dsn);
        self::assertSame($connection->settings, $disabled->settings);
        self::assertSame($connection->secrets, $disabled->secrets);
        self::assertFalse($disabled->enabled);
        self::assertFalse($disabled->default);
        self::assertSame($connection->priority, $disabled->priority);
        self::assertSame($connection->weight, $disabled->weight);
        self::assertSame($connection->webhookEnabled, $disabled->webhookEnabled);
    }
}

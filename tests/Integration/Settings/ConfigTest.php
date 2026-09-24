<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Settings;

use JooosiMail\Settings\Config;
use WP_UnitTestCase;

/**
 * Covers WordPress-backed config persistence behavior.
 *
 * @since 0.1.0
 */
final class ConfigTest extends WP_UnitTestCase
{
    /**
     * @since 0.1.0
     */
    public function testSetGetAndDeletePersistNestedConfigPaths(): void
    {
        delete_option('jooosi_mail_config');

        $config = new Config();

        self::assertTrue($config->set('settings.mail.intercept.enabled', false));
        self::assertFalse($config->get('settings.mail.intercept.enabled', true));
        self::assertSame(
            [
                'settings' => [
                    'mail' => [
                        'intercept' => [
                            'enabled' => false,
                        ],
                    ],
                ],
            ],
            get_option('jooosi_mail_config'),
        );

        self::assertTrue($config->delete('settings.mail.intercept.enabled'));
        self::assertFalse(get_option('jooosi_mail_config', false));
    }

    /**
     * @since 0.1.0
     */
    public function testAllReturnsTheSharedConfigPayload(): void
    {
        delete_option('jooosi_mail_config');

        $config = new Config();
        $payload = [
            'routing' => [
                'strategy' => 'weighted_random',
            ],
            'mail' => [
                'default_connection_id' => 42,
            ],
        ];

        self::assertTrue($config->set('', $payload));
        self::assertSame($payload, $config->all());
    }

    /**
     * @since 1.0.9
     */
    public function testSetManyPersistsSeveralPathsWithoutDiscardingExistingValues(): void
    {
        delete_option('jooosi_mail_config');

        $config = new Config();
        $config->set('settings.mail.intercept.enabled', false);

        self::assertTrue($config->setMany([
            'settings.delivery.mode' => 'sync',
            'settings.queue.retry.max_retries' => 5,
        ]));
        self::assertFalse($config->get('settings.mail.intercept.enabled', true));
        self::assertSame('sync', $config->get('settings.delivery.mode'));
        self::assertSame(5, $config->get('settings.queue.retry.max_retries'));
    }
}

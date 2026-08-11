<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Discovery;

use JooosiMail\Admin\Controller\DashboardController;
use JooosiMail\Cli\MailCommand;
use JooosiMail\Discovery\Runtime\AttributeDiscovery;
use JooosiMail\Mail\Profile\Builtin\SmtpProfile;
use JooosiMail\Mail\Transport\Bridge\MicrosoftGraph\Transport\MicrosoftGraphTransportFactory;
use JooosiMail\Queue\Handler\SendEmailHandler;
use JooosiMail\Settings\Config;
use WP_UnitTestCase;

/**
 * Covers the internal attribute discovery scanner.
 *
 * @since 1.0.8
 */
final class AttributeDiscoveryTest extends WP_UnitTestCase
{
    /**
     * @since 1.0.8
     */
    public function testDiscoversEverySupportedAttributeCategory(): void
    {
        $sourceDirectory = dirname(__DIR__, 3) . '/src';
        $manifest = (new AttributeDiscovery('JooosiMail', $sourceDirectory))->discover();

        self::assertContains(Config::class, $manifest->services);
        self::assertContains(DashboardController::class, $manifest->controllers);
        self::assertContains(MailCommand::class, $manifest->commands);
        self::assertContains(SmtpProfile::class, $manifest->profiles);
        self::assertContains(SendEmailHandler::class, $manifest->messageHandlers);
        self::assertContains(MicrosoftGraphTransportFactory::class, $manifest->transportFactories);
    }
}

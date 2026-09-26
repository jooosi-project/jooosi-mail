<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Connection;

use JooosiMail\Mail\Connection\ConnectionViewFactory;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Covers admin-safe connection payload rendering.
 *
 * @since 0.1.0
 */
final class ConnectionViewFactoryTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testListPayloadIncludesWebhookSupportForSupportedAndUnsupportedProfiles(): void
    {
        $supportedConnection = $this->connectionManager()->create([
            'profile' => 'sendgrid',
            'name' => 'SendGrid Summary Connection',
            'scheme' => 'sendgrid+api',
            'api_key' => 'sendgrid-api-key',
            'default' => false,
        ]);
        $unsupportedConnection = $this->createNullConnection([
            'name' => 'Null Summary Connection',
        ]);
        $connectionViewFactory = $this->container()->get(ConnectionViewFactory::class);

        $supportedPayload = $connectionViewFactory->createListItem($supportedConnection);
        $unsupportedPayload = $connectionViewFactory->createListItem($unsupportedConnection);

        self::assertTrue($supportedPayload['profile']['supportsWebhooks']);
        self::assertFalse($unsupportedPayload['profile']['supportsWebhooks']);
    }

    /**
     * @since 0.1.0
     */
    public function testDetailPayloadIncludesConditionalConfigurationMetadata(): void
    {
        $connection = $this->connectionManager()->create([
            'profile' => 'gmail',
            'name' => 'Gmail Conditional Connection',
            'scheme' => 'gmail+smtp',
            'username' => 'sender@example.com',
            'password' => 'app-password',
        ]);
        $connectionViewFactory = $this->container()->get(ConnectionViewFactory::class);
        $payload = $connectionViewFactory->createDetail($connection);

        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['gmail+smtp']],
        ], $payload['configurationFields']['username']['visibleWhen']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['gmail+api']],
        ], $payload['configurationFields']['service_account_email']['requiredWhen']);
    }

    /**
     * @since 0.1.0
     */
    public function testDetailPayloadIncludesTransportSchemeChoicesAndCredentialDescriptions(): void
    {
        $connection = $this->connectionManager()->create([
            'profile' => 'mailkite',
            'name' => 'MailKite Detail Connection',
            'scheme' => 'mailkite+smtp',
            'smtp_username' => 'mailkite-smtp-user',
            'smtp_password' => 'mailkite-smtp-password',
        ]);
        $connectionViewFactory = $this->container()->get(ConnectionViewFactory::class);
        $payload = $connectionViewFactory->createDetail($connection);

        self::assertSame(
            ['mailkite+api', 'mailkite+smtp', 'mailkite+smtps'],
            $payload['configurationFields']['scheme']['choices'],
        );
        self::assertArrayNotHasKey('description', $payload['configurationFields']['scheme']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['mailkite+api']],
        ], $payload['configurationFields']['api_key']['visibleWhen']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['mailkite+smtp', 'mailkite+smtps']],
        ], $payload['configurationFields']['smtp_password']['requiredWhen']);
        self::assertSame(
            'Enter this separately; the API key is not used automatically.',
            $payload['configurationFields']['smtp_password']['description'],
        );
    }

    /**
     * @since 0.1.0
     */
    public function testDetailPayloadMarksAliasedSecretDefaultsAsConfigured(): void
    {
        $connection = $this->connectionManager()->create([
            'profile' => 'zeptomail',
            'name' => 'ZeptoMail Legacy SMTP Connection',
            'scheme' => 'zeptomail+smtps',
            'api_token' => 'legacy-smtp-password',
        ]);
        $connectionViewFactory = $this->container()->get(ConnectionViewFactory::class);
        $payload = $connectionViewFactory->createDetail($connection);

        self::assertTrue($payload['configurationFields']['password']['configured']);
    }

    /**
     * @since 0.1.0
     */
    public function testDetailPayloadIncludesOptionalProfileMetadata(): void
    {
        $connection = $this->connectionManager()->create([
            'profile' => 'resend',
            'name' => 'Resend Metadata Connection',
            'scheme' => 'resend+api',
            'api_key' => 'resend-api-key',
        ]);
        $connectionViewFactory = $this->container()->get(ConnectionViewFactory::class);
        $payload = $connectionViewFactory->createDetail($connection);

        self::assertSame('Resend', $payload['profile']['label']);
        self::assertSame('Send mail through Resend using the Symfony bridge API or SMTP transport.', $payload['profile']['description']);
        self::assertSame('https://resend.com', $payload['profile']['metadata']['website']);
        self::assertSame('https://resend.com/docs', $payload['profile']['metadata']['docsUrl']);
        self::assertSame(['transactional'], $payload['profile']['metadata']['useCases']);
    }
}

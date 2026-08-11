<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Connection;

use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Connection\ConnectionDsnResolver;
use JooosiMail\Mail\Transport\TransportRegistry;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Covers third-party provider mail profiles.
 *
 * @since 0.1.0
 */
final class ProviderProfileTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 0.1.0
     */
    public function testProviderProfilesAreListedWithWebhookSupportAndSupportedSchemes(): void
    {
        $profiles = [];

        foreach ($this->connectionManager()->listProfiles() as $profile) {
            $profiles[(string) $profile['key']] = $profile;
        }

        self::assertSame(['sendgrid+api', 'sendgrid+smtp'], $profiles['sendgrid']['schemes']);
        self::assertSame('SendGrid', $profiles['sendgrid']['label']);
        self::assertSame('Send mail through SendGrid using the Symfony bridge API or SMTP transport.', $profiles['sendgrid']['description']);
        self::assertTrue((bool) $profiles['sendgrid']['supports_webhooks']);
        self::assertSame(['ahasend+api', 'ahasend+smtp'], $profiles['ahasend']['schemes']);
        self::assertTrue((bool) $profiles['ahasend']['supports_webhooks']);
        self::assertSame(['bird+api', 'bird+smtp', 'bird+smtps'], $profiles['bird']['schemes']);
        self::assertTrue((bool) $profiles['bird']['supports_webhooks']);
        self::assertSame(['brevo+api', 'brevo+smtp'], $profiles['brevo']['schemes']);
        self::assertTrue((bool) $profiles['brevo']['supports_webhooks']);
        self::assertSame(['cloudflare+api'], $profiles['cloudflare']['schemes']);
        self::assertFalse((bool) $profiles['cloudflare']['supports_webhooks']);
        self::assertSame(['azure+api'], $profiles['azure']['schemes']);
        self::assertFalse((bool) $profiles['azure']['supports_webhooks']);
        self::assertSame(['elasticemail+api', 'elasticemail+smtp', 'elasticemail+smtps'], $profiles['elasticemail']['schemes']);
        self::assertFalse((bool) $profiles['elasticemail']['supports_webhooks']);
        self::assertSame(['emailit+api', 'emailit+smtp', 'emailit+smtps'], $profiles['emailit']['schemes']);
        self::assertFalse((bool) $profiles['emailit']['supports_webhooks']);
        self::assertSame(['gmail+api', 'gmail+smtp'], $profiles['gmail']['schemes']);
        self::assertFalse((bool) $profiles['gmail']['supports_webhooks']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['gmail+smtp']],
        ], $profiles['gmail']['configuration_fields']['username']['visible_when']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['gmail+api']],
        ], $profiles['gmail']['configuration_fields']['service_account_email']['required_when']);
        self::assertSame(['zeptomail+api', 'zeptomail+smtp', 'zeptomail+smtps'], $profiles['zeptomail']['schemes']);
        self::assertTrue((bool) $profiles['zeptomail']['supports_webhooks']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['zeptomail+api']],
        ], $profiles['zeptomail']['configuration_fields']['api_token']['visible_when']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['zeptomail+smtp', 'zeptomail+smtps']],
        ], $profiles['zeptomail']['configuration_fields']['username']['visible_when']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['zeptomail+smtp', 'zeptomail+smtps']],
        ], $profiles['zeptomail']['configuration_fields']['password']['required_when']);
        self::assertSame(['mailgun+api', 'mailgun+https', 'mailgun+smtp'], $profiles['mailgun']['schemes']);
        self::assertTrue((bool) $profiles['mailgun']['supports_webhooks']);
        self::assertSame(['mailtrap+smtp', 'mailtrap+api', 'mailtrap+sandbox'], $profiles['mailtrap']['schemes']);
        self::assertTrue((bool) $profiles['mailtrap']['supports_webhooks']);
        self::assertSame([
            ['field' => 'scheme', 'operator' => 'in', 'values' => ['mailtrap+sandbox']],
        ], $profiles['mailtrap']['configuration_fields']['inbox_id']['required_when']);
        self::assertSame(['mailersend+api', 'mailersend+smtp'], $profiles['mailersend']['schemes']);
        self::assertTrue((bool) $profiles['mailersend']['supports_webhooks']);
        self::assertSame(['mailjet+api', 'mailjet+smtp'], $profiles['mailjet']['schemes']);
        self::assertTrue((bool) $profiles['mailjet']['supports_webhooks']);
        self::assertTrue((bool) $profiles['mailjet']['configuration_fields']['access_key']['required']);
        self::assertTrue((bool) $profiles['mailjet']['configuration_fields']['secret_key']['required']);
        self::assertSame(['mailomat+api', 'mailomat+smtp'], $profiles['mailomat']['schemes']);
        self::assertTrue((bool) $profiles['mailomat']['supports_webhooks']);
        self::assertSame(['mandrill+api', 'mandrill+https', 'mandrill+smtp'], $profiles['mandrill']['schemes']);
        self::assertTrue((bool) $profiles['mandrill']['supports_webhooks']);
        self::assertSame(['mailpace+api', 'mailpace+smtp'], $profiles['mailpace']['schemes']);
        self::assertFalse((bool) $profiles['mailpace']['supports_webhooks']);
        self::assertTrue((bool) $profiles['mailpace']['configuration_fields']['api_token']['required']);
        self::assertSame(['microsoftgraph+api'], $profiles['microsoftgraph']['schemes']);
        self::assertFalse((bool) $profiles['microsoftgraph']['supports_webhooks']);
        self::assertSame([
            ['field' => 'graph_endpoint', 'operator' => 'not_in', 'values' => ['', 'default', 'graph.microsoft.com']],
        ], $profiles['microsoftgraph']['configuration_fields']['auth_endpoint']['required_when']);
        self::assertSame(['infobip+api', 'infobip+smtp'], $profiles['infobip']['schemes']);
        self::assertFalse((bool) $profiles['infobip']['supports_webhooks']);
        self::assertSame(['pepipost+api', 'pepipost+smtp', 'pepipost+smtps'], $profiles['pepipost']['schemes']);
        self::assertFalse((bool) $profiles['pepipost']['supports_webhooks']);
        self::assertSame(['postal+api'], $profiles['postal']['schemes']);
        self::assertFalse((bool) $profiles['postal']['supports_webhooks']);
        self::assertSame(['postmark+api', 'postmark+smtp'], $profiles['postmark']['schemes']);
        self::assertTrue((bool) $profiles['postmark']['supports_webhooks']);
        self::assertSame(['resend+api', 'resend+smtp'], $profiles['resend']['schemes']);
        self::assertSame('Resend', $profiles['resend']['label']);
        self::assertSame('Send mail through Resend using the Symfony bridge API or SMTP transport.', $profiles['resend']['description']);
        self::assertTrue((bool) $profiles['resend']['supports_webhooks']);
        self::assertSame('https://resend.com', $profiles['resend']['metadata']['website']);
        self::assertSame('https://resend.com/docs', $profiles['resend']['metadata']['docsUrl']);
        self::assertSame(['transactional'], $profiles['resend']['metadata']['useCases']);
        self::assertSame(['scaleway+api', 'scaleway+smtp'], $profiles['scaleway']['schemes']);
        self::assertFalse((bool) $profiles['scaleway']['supports_webhooks']);
        self::assertSame(['sendlayer+api', 'sendlayer+smtp', 'sendlayer+smtps'], $profiles['sendlayer']['schemes']);
        self::assertTrue((bool) $profiles['sendlayer']['supports_webhooks']);
        self::assertSame(['sendpulse+api', 'sendpulse+smtp', 'sendpulse+smtps'], $profiles['sendpulse']['schemes']);
        self::assertFalse((bool) $profiles['sendpulse']['supports_webhooks']);
        self::assertSame(['ses+api', 'ses+https', 'ses+smtp'], $profiles['ses']['schemes']);
        self::assertFalse((bool) $profiles['ses']['supports_webhooks']);
        self::assertSame(['smtp2go+api', 'smtp2go+smtp', 'smtp2go+smtps'], $profiles['smtp2go']['schemes']);
        self::assertTrue((bool) $profiles['smtp2go']['supports_webhooks']);
        self::assertSame(['smtpcom+api', 'smtpcom+smtp', 'smtpcom+smtps'], $profiles['smtpcom']['schemes']);
        self::assertFalse((bool) $profiles['smtpcom']['supports_webhooks']);
        self::assertSame(['sparkpost+api', 'sparkpost+smtp', 'sparkpost+smtps'], $profiles['sparkpost']['schemes']);
        self::assertTrue((bool) $profiles['sparkpost']['supports_webhooks']);
        self::assertSame(['sweego+api', 'sweego+smtp'], $profiles['sweego']['schemes']);
        self::assertTrue((bool) $profiles['sweego']['supports_webhooks']);
        self::assertSame(['tosend+api'], $profiles['tosend']['schemes']);
        self::assertTrue((bool) $profiles['tosend']['supports_webhooks']);
        self::assertSame(['zohomail+smtp', 'zohomail+smtps'], $profiles['zohomail']['schemes']);
        self::assertFalse((bool) $profiles['zohomail']['supports_webhooks']);
    }

    /**
     * @since 0.1.0
     */
    public function testSendGridProfileBuildsApiAndSmtpTransports(): void
    {
        $apiConnection = $this->assertResolvedTransport([
            'profile' => 'sendgrid',
            'name' => 'SendGrid API',
            'scheme' => 'sendgrid+api',
            'api_key' => 'sendgrid-api-key',
            'region' => 'eu',
            'webhook_enabled' => true,
            'webhook_secret' => 'sendgrid-webhook-secret',
        ], 'sendgrid+api://sendgrid-api-key@default?region=eu', 'Symfony\\Component\\Mailer\\Bridge\\Sendgrid\\Transport\\SendgridApiTransport');

        self::assertTrue($apiConnection->webhookEnabled);
        self::assertSame('sendgrid-webhook-secret', $apiConnection->getWebhookSecret());

        $this->assertResolvedTransport([
            'profile' => 'sendgrid',
            'name' => 'SendGrid SMTP',
            'scheme' => 'sendgrid+smtp',
            'api_key' => 'sendgrid-smtp-key',
        ], 'sendgrid+smtp://sendgrid-smtp-key@default', 'Symfony\\Component\\Mailer\\Bridge\\Sendgrid\\Transport\\SendgridSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testAhaSendProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'ahasend',
            'name' => 'AhaSend API',
            'scheme' => 'ahasend+api',
            'api_key' => 'ahasend-api-key',
            'webhook_enabled' => true,
            'webhook_secret' => 'ahasend-secret',
        ], 'ahasend+api://ahasend-api-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\AhaSend\\Transport\\AhaSendApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'ahasend',
            'name' => 'AhaSend SMTP',
            'scheme' => 'ahasend+smtp',
            'username' => 'ahasend-user',
            'password' => 'ahasend-pass',
        ], 'ahasend+smtp://ahasend-user:ahasend-pass@default', 'JooosiMail\\Mail\\Transport\\Bridge\\AhaSend\\Transport\\AhaSendSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testBrevoProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'brevo',
            'name' => 'Brevo API',
            'scheme' => 'brevo+api',
            'api_key' => 'brevo-api-key',
            'webhook_enabled' => true,
        ], 'brevo+api://brevo-api-key@default', 'Symfony\\Component\\Mailer\\Bridge\\Brevo\\Transport\\BrevoApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'brevo',
            'name' => 'Brevo SMTP',
            'scheme' => 'brevo+smtp',
            'username' => 'brevo-user',
            'password' => 'brevo-pass',
        ], 'brevo+smtp://brevo-user:brevo-pass@default', 'Symfony\\Component\\Mailer\\Bridge\\Brevo\\Transport\\BrevoSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testCloudflareProfileBuildsApiTransport(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'cloudflare',
            'name' => 'Cloudflare API',
            'scheme' => 'cloudflare+api',
            'account_id' => 'cloudflare-account-id',
            'api_token' => 'cloudflare-api-token',
        ], 'cloudflare+api://cloudflare-account-id:cloudflare-api-token@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Cloudflare\\Transport\\CloudflareApiTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testAzureProfileBuildsApiTransport(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'azure',
            'name' => 'Azure API',
            'scheme' => 'azure+api',
            'resource_name' => 'jooosimail-resource',
            'api_key' => 'azure-api-key',
            'api_version' => '2024-07-01-preview',
            'disable_tracking' => 'true',
        ], 'azure+api://jooosimail-resource:azure-api-key@default?api_version=2024-07-01-preview&disable_tracking=true', 'JooosiMail\\Mail\\Transport\\Bridge\\Azure\\Transport\\AzureApiTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testBirdProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'bird',
            'name' => 'Bird API',
            'scheme' => 'bird+api',
            'access_key' => 'bird-api-key',
            'workspace_id' => 'workspace-api',
            'region' => 'us',
        ], 'bird+api://bird-api-key@default?workspace_id=workspace-api&region=us', 'JooosiMail\\Mail\\Transport\\Bridge\\Bird\\Transport\\BirdApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'bird',
            'name' => 'Bird SMTP',
            'scheme' => 'bird+smtp',
            'access_key' => 'bird-smtp-key',
            'workspace_id' => 'workspace-smtp',
            'region' => 'eu',
        ], 'bird+smtp://bird-smtp-key@default?workspace_id=workspace-smtp&region=eu', 'JooosiMail\\Mail\\Transport\\Bridge\\Bird\\Transport\\BirdSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testElasticEmailProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'elasticemail',
            'name' => 'Elastic Email API',
            'scheme' => 'elasticemail+api',
            'api_key' => 'elastic-api-key',
        ], 'elasticemail+api://elastic-api-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\ElasticEmail\\Transport\\ElasticEmailApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'elasticemail',
            'name' => 'Elastic Email SMTPS',
            'scheme' => 'elasticemail+smtps',
            'username' => 'elastic-user',
            'password' => 'elastic-pass',
        ], 'elasticemail+smtps://elastic-user:elastic-pass@default', 'JooosiMail\\Mail\\Transport\\Bridge\\ElasticEmail\\Transport\\ElasticEmailSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testEmailitProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'emailit',
            'name' => 'Emailit API',
            'scheme' => 'emailit+api',
            'api_key' => 'emailit-api-key',
        ], 'emailit+api://emailit-api-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Emailit\\Transport\\EmailitApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'emailit',
            'name' => 'Emailit SMTP',
            'scheme' => 'emailit+smtp',
            'smtp_credential' => 'emailit-smtp-credential',
        ], 'emailit+smtp://emailit:emailit-smtp-credential@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Emailit\\Transport\\EmailitSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testGmailProfileBuildsApiAndSmtpTransports(): void
    {
        $privateKey = "-----BEGIN PRIVATE KEY-----\nfake-private-key\n-----END PRIVATE KEY-----";

        $this->assertResolvedTransport([
            'profile' => 'gmail',
            'name' => 'Gmail API',
            'scheme' => 'gmail+api',
            'service_account_email' => 'service@example.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'user_email' => 'sender@example.com',
        ], 'gmail+api://service%40example.iam.gserviceaccount.com:' . rawurlencode(base64_encode($privateKey)) . '@default?user=sender%40example.com', 'JooosiMail\\Mail\\Transport\\Bridge\\Gmail\\Transport\\GmailApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'gmail',
            'name' => 'Gmail SMTP',
            'scheme' => 'gmail+smtp',
            'username' => 'mailer@gmail.com',
            'password' => 'gmail-app-password',
        ], 'gmail+smtp://mailer%40gmail.com:gmail-app-password@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Gmail\\Transport\\GmailSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testPepipostProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'pepipost',
            'name' => 'Pepipost API',
            'scheme' => 'pepipost+api',
            'api_key' => 'pepipost-api-key',
            'region' => 'eu',
        ], 'pepipost+api://pepipost-api-key@default?region=eu', 'JooosiMail\\Mail\\Transport\\Bridge\\Pepipost\\Transport\\PepipostApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'pepipost',
            'name' => 'Pepipost SMTPS',
            'scheme' => 'pepipost+smtps',
            'username' => 'pepipost-user',
            'password' => 'pepipost-pass',
        ], 'pepipost+smtps://pepipost-user:pepipost-pass@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Pepipost\\Transport\\PepipostSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testZeptoMailProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'zeptomail',
            'name' => 'ZeptoMail API',
            'scheme' => 'zeptomail+api',
            'api_token' => 'zeptomail-api-token',
            'webhook_enabled' => true,
        ], 'zeptomail+api://zeptomail-api-token@default', 'JooosiMail\\Mail\\Transport\\Bridge\\ZeptoMail\\Transport\\ZeptoMailApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'zeptomail',
            'name' => 'ZeptoMail SMTPS',
            'scheme' => 'zeptomail+smtps',
            'username' => 'emailapikey',
            'password' => 'zeptomail-smtp-password',
        ], 'zeptomail+smtps://emailapikey:zeptomail-smtp-password@default', 'JooosiMail\\Mail\\Transport\\Bridge\\ZeptoMail\\Transport\\ZeptoMailSmtpTransport');

        $this->assertResolvedTransport([
            'profile' => 'zeptomail',
            'name' => 'ZeptoMail Legacy SMTPS',
            'scheme' => 'zeptomail+smtps',
            'api_token' => 'zeptomail-smtp-token',
        ], 'zeptomail+smtps://emailapikey:zeptomail-smtp-token@default', 'JooosiMail\\Mail\\Transport\\Bridge\\ZeptoMail\\Transport\\ZeptoMailSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMailgunProfileBuildsApiHttpAndSmtpTransports(): void
    {
        $apiConnection = $this->assertResolvedTransport([
            'profile' => 'mailgun',
            'name' => 'Mailgun API',
            'scheme' => 'mailgun+api',
            'api_key' => 'mailgun-api-key',
            'domain' => 'mg.example.com',
            'region' => 'eu',
            'webhook_enabled' => true,
            'webhook_secret' => 'mailgun-webhook-secret',
        ], 'mailgun+api://mailgun-api-key:mg.example.com@default?region=eu', 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunApiTransport');

        self::assertTrue($apiConnection->webhookEnabled);
        self::assertSame('mailgun-webhook-secret', $apiConnection->getWebhookSecret());

        $this->assertResolvedTransport([
            'profile' => 'mailgun',
            'name' => 'Mailgun HTTP',
            'scheme' => 'mailgun+https',
            'api_key' => 'mailgun-http-key',
            'domain' => 'mg-http.example.com',
        ], 'mailgun+https://mailgun-http-key:mg-http.example.com@default', 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunHttpTransport');

        $this->assertResolvedTransport([
            'profile' => 'mailgun',
            'name' => 'Mailgun SMTP',
            'scheme' => 'mailgun+smtp',
            'username' => 'postmaster@mg.example.com',
            'password' => 'mailgun-smtp-pass',
            'region' => 'eu',
        ], 'mailgun+smtp://postmaster%40mg.example.com:mailgun-smtp-pass@default?region=eu', 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMailtrapProfileBuildsApiSandboxAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'mailtrap',
            'name' => 'Mailtrap API',
            'scheme' => 'mailtrap+api',
            'token' => 'mailtrap-api-token',
            'webhook_enabled' => true,
        ], 'mailtrap+api://mailtrap-api-token@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Mailtrap\\Transport\\MailtrapApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'mailtrap',
            'name' => 'Mailtrap Sandbox',
            'scheme' => 'mailtrap+sandbox',
            'token' => 'mailtrap-sandbox-token',
            'inbox_id' => 123,
            'webhook_enabled' => true,
        ], 'mailtrap+sandbox://mailtrap-sandbox-token@default?inboxId=123', 'JooosiMail\\Mail\\Transport\\Bridge\\Mailtrap\\Transport\\MailtrapApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'mailtrap',
            'name' => 'Mailtrap SMTP',
            'scheme' => 'mailtrap+smtp',
            'password' => 'mailtrap-smtp-password',
        ], 'mailtrap+smtp://mailtrap-smtp-password@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Mailtrap\\Transport\\MailtrapSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMailerSendProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'mailersend',
            'name' => 'MailerSend API',
            'scheme' => 'mailersend+api',
            'api_key' => 'mailersend-api-key',
            'webhook_enabled' => true,
            'webhook_secret' => 'mailersend-secret',
        ], 'mailersend+api://mailersend-api-key@default', 'Symfony\\Component\\Mailer\\Bridge\\MailerSend\\Transport\\MailerSendApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'mailersend',
            'name' => 'MailerSend SMTP',
            'scheme' => 'mailersend+smtp',
            'username' => 'mailer-send-user',
            'password' => 'mailer-send-pass',
        ], 'mailersend+smtp://mailer-send-user:mailer-send-pass@default', 'Symfony\\Component\\Mailer\\Bridge\\MailerSend\\Transport\\MailerSendSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMailjetProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'mailjet',
            'name' => 'Mailjet API',
            'scheme' => 'mailjet+api',
            'access_key' => 'mailjet-access-key',
            'secret_key' => 'mailjet-secret-key',
            'sandbox' => 'true',
            'webhook_enabled' => true,
        ], 'mailjet+api://mailjet-access-key:mailjet-secret-key@default?sandbox=true', 'Symfony\\Component\\Mailer\\Bridge\\Mailjet\\Transport\\MailjetApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'mailjet',
            'name' => 'Mailjet SMTP',
            'scheme' => 'mailjet+smtp',
            'access_key' => 'mailjet-smtp-access',
            'secret_key' => 'mailjet-smtp-secret',
        ], 'mailjet+smtp://mailjet-smtp-access:mailjet-smtp-secret@default', 'Symfony\\Component\\Mailer\\Bridge\\Mailjet\\Transport\\MailjetSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMailomatProfileBuildsApiAndSmtpTransports(): void
    {
        $apiConnection = $this->assertResolvedTransport([
            'profile' => 'mailomat',
            'name' => 'Mailomat API',
            'scheme' => 'mailomat+api',
            'api_key' => 'mailomat-api-key',
            'webhook_enabled' => true,
            'webhook_secret' => 'mailomat-webhook-secret',
        ], 'mailomat+api://mailomat-api-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Mailomat\\Transport\\MailomatApiTransport');

        self::assertSame('mailomat-webhook-secret', $apiConnection->getWebhookSecret());

        $this->assertResolvedTransport([
            'profile' => 'mailomat',
            'name' => 'Mailomat SMTP',
            'scheme' => 'mailomat+smtp',
            'username' => 'mailer@example.com',
            'password' => 'mailomat-smtp-password',
        ], 'mailomat+smtp://mailer%40example.com:mailomat-smtp-password@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Mailomat\\Transport\\MailomatSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMandrillProfileBuildsApiHttpAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'mandrill',
            'name' => 'Mandrill API',
            'scheme' => 'mandrill+api',
            'api_key' => 'mandrill-api-key',
            'webhook_enabled' => true,
            'webhook_secret' => 'mandrill-webhook-secret',
        ], 'mandrill+api://mandrill-api-key@default', 'Symfony\\Component\\Mailer\\Bridge\\Mailchimp\\Transport\\MandrillApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'mandrill',
            'name' => 'Mandrill HTTP',
            'scheme' => 'mandrill+https',
            'api_key' => 'mandrill-http-key',
        ], 'mandrill+https://mandrill-http-key@default', 'Symfony\\Component\\Mailer\\Bridge\\Mailchimp\\Transport\\MandrillHttpTransport');

        $this->assertResolvedTransport([
            'profile' => 'mandrill',
            'name' => 'Mandrill SMTP',
            'scheme' => 'mandrill+smtp',
            'username' => 'mandrill-user',
            'password' => 'mandrill-pass',
        ], 'mandrill+smtp://mandrill-user:mandrill-pass@default', 'Symfony\\Component\\Mailer\\Bridge\\Mailchimp\\Transport\\MandrillSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMicrosoftGraphProfileBuildsApiTransport(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'microsoftgraph',
            'name' => 'Microsoft Graph API',
            'scheme' => 'microsoftgraph+api',
            'client_app_id' => 'microsoft-client-id',
            'client_app_secret' => 'microsoft-client-secret',
            'tenant_id' => 'tenant-id-1',
            'graph_endpoint' => 'microsoftgraph.chinacloudapi.cn',
            'auth_endpoint' => 'login.partner.microsoftonline.cn',
            'no_save' => 'true',
        ], 'microsoftgraph+api://microsoft-client-id:microsoft-client-secret@microsoftgraph.chinacloudapi.cn?tenantId=tenant-id-1&authEndpoint=login.partner.microsoftonline.cn&noSave=true', 'JooosiMail\\Mail\\Transport\\Bridge\\MicrosoftGraph\\Transport\\MicrosoftGraphApiTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testPostmarkProfileBuildsApiAndSmtpTransports(): void
    {
        $apiConnection = $this->assertResolvedTransport([
            'profile' => 'postmark',
            'name' => 'Postmark API',
            'scheme' => 'postmark+api',
            'api_key' => 'postmark-token',
            'webhook_enabled' => true,
        ], 'postmark+api://postmark-token@default', 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkApiTransport');

        self::assertTrue($apiConnection->webhookEnabled);

        $this->assertResolvedTransport([
            'profile' => 'postmark',
            'name' => 'Postmark SMTP',
            'scheme' => 'postmark+smtp',
            'api_key' => 'postmark-smtp-token',
        ], 'postmark+smtp://postmark-smtp-token@default', 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testPostalProfileBuildsApiTransport(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'postal',
            'name' => 'Postal API',
            'scheme' => 'postal+api',
            'api_key' => 'postal-api-key',
            'host' => 'postal.example.com',
            'port' => 8443,
        ], 'postal+api://postal-api-key@postal.example.com:8443', 'JooosiMail\\Mail\\Transport\\Bridge\\Postal\\Transport\\PostalApiTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMailPaceProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'mailpace',
            'name' => 'MailPace API',
            'scheme' => 'mailpace+api',
            'api_token' => 'mailpace-api-token',
        ], 'mailpace+api://mailpace-api-token@default', 'Symfony\\Component\\Mailer\\Bridge\\MailPace\\Transport\\MailPaceApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'mailpace',
            'name' => 'MailPace SMTP',
            'scheme' => 'mailpace+smtp',
            'api_token' => 'mailpace-smtp-token',
        ], 'mailpace+smtp://mailpace-smtp-token@default', 'Symfony\\Component\\Mailer\\Bridge\\MailPace\\Transport\\MailPaceSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testInfobipProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'infobip',
            'name' => 'Infobip API',
            'scheme' => 'infobip+api',
            'api_key' => 'infobip-api-key',
            'host' => 'api.infobip.example',
        ], 'infobip+api://infobip-api-key@api.infobip.example', 'Symfony\\Component\\Mailer\\Bridge\\Infobip\\Transport\\InfobipApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'infobip',
            'name' => 'Infobip SMTP',
            'scheme' => 'infobip+smtp',
            'api_key' => 'infobip-smtp-key',
        ], 'infobip+smtp://infobip-smtp-key@default', 'Symfony\\Component\\Mailer\\Bridge\\Infobip\\Transport\\InfobipSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testSendLayerProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'sendlayer',
            'name' => 'SendLayer API',
            'scheme' => 'sendlayer+api',
            'api_key' => 'sendlayer-api-key',
        ], 'sendlayer+api://sendlayer-api-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\SendLayer\\Transport\\SendLayerApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'sendlayer',
            'name' => 'SendLayer SMTP',
            'scheme' => 'sendlayer+smtp',
            'username' => 'sendlayer-user',
            'password' => 'sendlayer-pass',
        ], 'sendlayer+smtp://sendlayer-user:sendlayer-pass@default', 'JooosiMail\\Mail\\Transport\\Bridge\\SendLayer\\Transport\\SendLayerSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testSendPulseProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'sendpulse',
            'name' => 'SendPulse API',
            'scheme' => 'sendpulse+api',
            'client_id' => 'sendpulse-client-id',
            'client_secret' => 'sendpulse-client-secret',
        ], 'sendpulse+api://sendpulse-client-id:sendpulse-client-secret@default', 'JooosiMail\\Mail\\Transport\\Bridge\\SendPulse\\Transport\\SendPulseApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'sendpulse',
            'name' => 'SendPulse SMTPS',
            'scheme' => 'sendpulse+smtps',
            'username' => 'sendpulse-user',
            'password' => 'sendpulse-pass',
        ], 'sendpulse+smtps://sendpulse-user:sendpulse-pass@default', 'JooosiMail\\Mail\\Transport\\Bridge\\SendPulse\\Transport\\SendPulseSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testSmtp2goProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'smtp2go',
            'name' => 'SMTP2GO API',
            'scheme' => 'smtp2go+api',
            'api_key' => 'smtp2go-api-key',
            'region' => 'eu',
        ], 'smtp2go+api://smtp2go-api-key@default?region=eu', 'JooosiMail\\Mail\\Transport\\Bridge\\Smtp2go\\Transport\\Smtp2goApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'smtp2go',
            'name' => 'SMTP2GO SMTPS',
            'scheme' => 'smtp2go+smtps',
            'username' => 'smtp2go-user',
            'password' => 'smtp2go-pass',
            'region' => 'au',
        ], 'smtp2go+smtps://smtp2go-user:smtp2go-pass@default?region=au', 'JooosiMail\\Mail\\Transport\\Bridge\\Smtp2go\\Transport\\Smtp2goSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testSmtpComProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'smtpcom',
            'name' => 'SMTP.com API',
            'scheme' => 'smtpcom+api',
            'api_key' => 'smtpcom-api-key',
            'channel' => 'transactional',
        ], 'smtpcom+api://smtpcom-api-key@default?channel=transactional', 'JooosiMail\\Mail\\Transport\\Bridge\\SmtpCom\\Transport\\SmtpComApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'smtpcom',
            'name' => 'SMTP.com SMTP',
            'scheme' => 'smtpcom+smtp',
            'username' => 'smtpcom-user',
            'password' => 'smtpcom-pass',
        ], 'smtpcom+smtp://smtpcom-user:smtpcom-pass@default', 'JooosiMail\\Mail\\Transport\\Bridge\\SmtpCom\\Transport\\SmtpComSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testSparkPostProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'sparkpost',
            'name' => 'SparkPost API',
            'scheme' => 'sparkpost+api',
            'api_key' => 'sparkpost-api-key',
            'region' => 'eu',
        ], 'sparkpost+api://sparkpost-api-key@default?region=eu', 'JooosiMail\\Mail\\Transport\\Bridge\\SparkPost\\Transport\\SparkPostApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'sparkpost',
            'name' => 'SparkPost SMTP',
            'scheme' => 'sparkpost+smtp',
            'api_key' => 'sparkpost-smtp-key',
            'region' => 'eu',
        ], 'sparkpost+smtp://sparkpost-smtp-key@default?region=eu', 'JooosiMail\\Mail\\Transport\\Bridge\\SparkPost\\Transport\\SparkPostSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testResendProfileBuildsApiAndSmtpTransports(): void
    {
        $apiConnection = $this->assertResolvedTransport([
            'profile' => 'resend',
            'name' => 'Resend API',
            'scheme' => 'resend+api',
            'api_key' => 'resend-key',
            'webhook_enabled' => true,
            'webhook_secret' => 'resend-webhook-secret',
        ], 'resend+api://resend-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Resend\\Transport\\ResendApiTransport');

        self::assertTrue($apiConnection->webhookEnabled);
        self::assertSame('resend-webhook-secret', $apiConnection->getWebhookSecret());

        $this->assertResolvedTransport([
            'profile' => 'resend',
            'name' => 'Resend SMTP',
            'scheme' => 'resend+smtp',
            'api_key' => 'resend-smtp-key',
        ], 'resend+smtp://resend:resend-smtp-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Resend\\Transport\\ResendSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testScalewayProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'scaleway',
            'name' => 'Scaleway API',
            'scheme' => 'scaleway+api',
            'project_id' => 'project-id-1',
            'api_key' => 'scaleway-api-key',
            'region' => 'fr-par',
        ], 'scaleway+api://project-id-1:scaleway-api-key@default?region=fr-par', 'Symfony\\Component\\Mailer\\Bridge\\Scaleway\\Transport\\ScalewayApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'scaleway',
            'name' => 'Scaleway SMTP',
            'scheme' => 'scaleway+smtp',
            'project_id' => 'project-id-1',
            'api_key' => 'scaleway-smtp-key',
        ], 'scaleway+smtp://project-id-1:scaleway-smtp-key@default', 'Symfony\\Component\\Mailer\\Bridge\\Scaleway\\Transport\\ScalewaySmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testSesProfileBuildsApiHttpAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'ses',
            'name' => 'SES API',
            'scheme' => 'ses+api',
            'access_key' => 'ses-access-key',
            'secret_key' => 'ses-secret-key',
            'region' => 'us-east-1',
            'session_token' => 'ses-session-token',
        ], 'ses+api://ses-access-key:ses-secret-key@default?region=us-east-1&session_token=ses-session-token', 'Symfony\\Component\\Mailer\\Bridge\\Amazon\\Transport\\SesApiAsyncAwsTransport');

        $this->assertResolvedTransport([
            'profile' => 'ses',
            'name' => 'SES HTTP',
            'scheme' => 'ses+https',
            'access_key' => 'ses-http-access',
            'secret_key' => 'ses-http-secret',
        ], 'ses+https://ses-http-access:ses-http-secret@default', 'Symfony\\Component\\Mailer\\Bridge\\Amazon\\Transport\\SesHttpAsyncAwsTransport');

        $this->assertResolvedTransport([
            'profile' => 'ses',
            'name' => 'SES SMTP',
            'scheme' => 'ses+smtp',
            'username' => 'ses-smtp-user',
            'password' => 'ses-smtp-pass',
            'region' => 'us-east-1',
        ], 'ses+smtp://ses-smtp-user:ses-smtp-pass@default?region=us-east-1', 'Symfony\\Component\\Mailer\\Bridge\\Amazon\\Transport\\SesSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testSweegoProfileBuildsApiAndSmtpTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'sweego',
            'name' => 'Sweego API',
            'scheme' => 'sweego+api',
            'api_key' => 'sweego-api-key',
            'webhook_enabled' => true,
            'webhook_secret' => base64_encode('sweego-shared-secret'),
        ], 'sweego+api://sweego-api-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\Sweego\\Transport\\SweegoApiTransport');

        $this->assertResolvedTransport([
            'profile' => 'sweego',
            'name' => 'Sweego SMTP',
            'scheme' => 'sweego+smtp',
            'host' => 'smtp.sweego.example',
            'port' => 465,
            'username' => 'sweego-user',
            'password' => 'sweego-pass',
        ], 'sweego+smtp://sweego-user:sweego-pass@smtp.sweego.example:465', 'JooosiMail\\Mail\\Transport\\Bridge\\Sweego\\Transport\\SweegoSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testToSendProfileBuildsApiTransport(): void
    {
        $apiConnection = $this->assertResolvedTransport([
            'profile' => 'tosend',
            'name' => 'toSend API',
            'scheme' => 'tosend+api',
            'api_key' => 'tosend-api-key',
            'webhook_enabled' => true,
            'webhook_secret' => 'tosend-webhook-secret',
        ], 'tosend+api://tosend-api-key@default', 'JooosiMail\\Mail\\Transport\\Bridge\\ToSend\\Transport\\ToSendApiTransport');

        self::assertTrue($apiConnection->webhookEnabled);
        self::assertSame('tosend-webhook-secret', $apiConnection->getWebhookSecret());
    }

    /**
     * @since 0.1.0
     */
    public function testZohoMailProfileBuildsSmtpAndSmtpsTransports(): void
    {
        $this->assertResolvedTransport([
            'profile' => 'zohomail',
            'name' => 'Zoho Mail SMTP',
            'scheme' => 'zohomail+smtp',
            'username' => 'mailer@example.com',
            'password' => 'zoho-password',
            'account_type' => 'business',
        ], 'zohomail+smtp://mailer%40example.com:zoho-password@default?account_type=business', 'JooosiMail\\Mail\\Transport\\Bridge\\ZohoMail\\Transport\\ZohoMailSmtpTransport');

        $this->assertResolvedTransport([
            'profile' => 'zohomail',
            'name' => 'Zoho Mail SMTPS',
            'scheme' => 'zohomail+smtps',
            'username' => 'mailer@example.com',
            'password' => 'zoho-password',
        ], 'zohomail+smtps://mailer%40example.com:zoho-password@default', 'JooosiMail\\Mail\\Transport\\Bridge\\ZohoMail\\Transport\\ZohoMailSmtpTransport');
    }

    /**
     * @since 0.1.0
     */
    public function testMailgunSmtpProfileRequiresUsernameAndPassword(): void
    {
        $this->expectExceptionMessage('Configuration field "password" is required for profile "mailgun" when using scheme "mailgun+smtp".');

        $this->connectionManager()->create([
            'profile' => 'mailgun',
            'name' => 'Invalid Mailgun SMTP',
            'scheme' => 'mailgun+smtp',
            'username' => 'postmaster@mg.example.com',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testAhaSendProfileRequiresWebhookSecretWhenEnabled(): void
    {
        $this->expectExceptionMessage('Webhook secret is required for profile "ahasend" when webhooks are enabled.');

        $this->connectionManager()->create([
            'profile' => 'ahasend',
            'name' => 'AhaSend Webhooks',
            'api_key' => 'ahasend-api-key',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testMailomatProfileRequiresWebhookSecretWhenEnabled(): void
    {
        $this->expectExceptionMessage('Webhook secret is required for profile "mailomat" when webhooks are enabled.');

        $this->connectionManager()->create([
            'profile' => 'mailomat',
            'name' => 'Mailomat Webhooks',
            'api_key' => 'mailomat-api-key',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testToSendProfileRequiresWebhookSecretWhenEnabled(): void
    {
        $this->expectExceptionMessage('Webhook secret is required for profile "tosend" when webhooks are enabled.');

        $this->connectionManager()->create([
            'profile' => 'tosend',
            'name' => 'toSend Webhooks',
            'api_key' => 'tosend-api-key',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testGmailProfileRequiresUsernameAndPassword(): void
    {
        $this->expectExceptionMessage('Configuration field "password" is required for profile "gmail" when using scheme "gmail+smtp".');

        $this->connectionManager()->create([
            'profile' => 'gmail',
            'name' => 'Invalid Gmail SMTP',
            'scheme' => 'gmail+smtp',
            'username' => 'mailer@gmail.com',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testGmailApiProfileRequiresDelegatedUserEmail(): void
    {
        $this->expectExceptionMessage('Configuration field "user_email" is required for profile "gmail" when using scheme "gmail+api".');

        $this->connectionManager()->create([
            'profile' => 'gmail',
            'name' => 'Invalid Gmail API',
            'scheme' => 'gmail+api',
            'service_account_email' => 'service@example.iam.gserviceaccount.com',
            'private_key' => '-----BEGIN PRIVATE KEY-----\nfake-private-key\n-----END PRIVATE KEY-----',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testEmailitSmtpProfileRequiresSmtpCredential(): void
    {
        $this->expectExceptionMessage('Configuration field "smtp_credential" is required for profile "emailit" when using scheme "emailit+smtp".');

        $this->connectionManager()->create([
            'profile' => 'emailit',
            'name' => 'Invalid Emailit SMTP',
            'scheme' => 'emailit+smtp',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testSendPulseApiProfileRequiresClientSecret(): void
    {
        $this->expectExceptionMessage('Configuration field "client_secret" is required for profile "sendpulse" when using scheme "sendpulse+api".');

        $this->connectionManager()->create([
            'profile' => 'sendpulse',
            'name' => 'Invalid SendPulse API',
            'scheme' => 'sendpulse+api',
            'client_id' => 'sendpulse-client-id',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testInfobipApiProfileRequiresHost(): void
    {
        $this->expectExceptionMessage('Configuration field "host" is required for profile "infobip" when using scheme "infobip+api".');

        $this->connectionManager()->create([
            'profile' => 'infobip',
            'name' => 'Invalid Infobip API',
            'scheme' => 'infobip+api',
            'api_key' => 'infobip-api-key',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testMailtrapSandboxProfileRequiresInboxId(): void
    {
        $this->expectExceptionMessage('Configuration field "inbox_id" is required for profile "mailtrap" when using scheme "mailtrap+sandbox".');

        $this->connectionManager()->create([
            'profile' => 'mailtrap',
            'name' => 'Invalid Mailtrap Sandbox',
            'scheme' => 'mailtrap+sandbox',
            'token' => 'mailtrap-token',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testMandrillSmtpProfileRequiresUsernameAndPassword(): void
    {
        $this->expectExceptionMessage('Configuration field "password" is required for profile "mandrill" when using scheme "mandrill+smtp".');

        $this->connectionManager()->create([
            'profile' => 'mandrill',
            'name' => 'Invalid Mandrill SMTP',
            'scheme' => 'mandrill+smtp',
            'username' => 'mandrill-user',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testSesApiProfileRequiresAccessAndSecretKeys(): void
    {
        $this->expectExceptionMessage('Configuration field "secret_key" is required for profile "ses" when using scheme "ses+api".');

        $this->connectionManager()->create([
            'profile' => 'ses',
            'name' => 'Invalid SES API',
            'scheme' => 'ses+api',
            'access_key' => 'ses-access-key',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testMailPaceProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "mailpace" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'mailpace',
            'name' => 'MailPace Webhooks',
            'api_token' => 'mailpace-token',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testMicrosoftGraphCustomEndpointRequiresAuthEndpoint(): void
    {
        $this->expectExceptionMessage('Configuration field "auth_endpoint" is required for profile "microsoftgraph" when using a custom graph endpoint.');

        $this->connectionManager()->create([
            'profile' => 'microsoftgraph',
            'name' => 'Microsoft Graph China',
            'client_app_id' => 'client-id',
            'client_app_secret' => 'client-secret',
            'tenant_id' => 'tenant-id',
            'graph_endpoint' => 'microsoftgraph.chinacloudapi.cn',
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testAzureProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "azure" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'azure',
            'name' => 'Azure Webhooks',
            'resource_name' => 'jooosimail-resource',
            'api_key' => 'azure-api-key',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testGmailProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "gmail" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'gmail',
            'name' => 'Gmail Webhooks',
            'username' => 'mailer@gmail.com',
            'password' => 'gmail-app-password',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testSmtpComProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "smtpcom" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'smtpcom',
            'name' => 'SMTP.com Webhooks',
            'api_key' => 'smtpcom-api-key',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testZohoMailProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "zohomail" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'zohomail',
            'name' => 'Zoho Mail Webhooks',
            'username' => 'mailer@example.com',
            'password' => 'zoho-password',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testPostalProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "postal" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'postal',
            'name' => 'Postal Webhooks',
            'api_key' => 'postal-api-key',
            'host' => 'postal.example.com',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testScalewayProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "scaleway" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'scaleway',
            'name' => 'Scaleway Webhooks',
            'project_id' => 'project-id-1',
            'api_key' => 'scaleway-api-key',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testSesProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "ses" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'ses',
            'name' => 'SES Webhooks',
            'access_key' => 'ses-access-key',
            'secret_key' => 'ses-secret-key',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testMicrosoftGraphProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "microsoftgraph" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'microsoftgraph',
            'name' => 'Microsoft Graph Webhooks',
            'client_app_id' => 'client-id',
            'client_app_secret' => 'client-secret',
            'tenant_id' => 'tenant-id',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testCloudflareProfileRejectsWebhookEnablement(): void
    {
        $this->expectExceptionMessage('Profile "cloudflare" does not support webhooks.');

        $this->connectionManager()->create([
            'profile' => 'cloudflare',
            'name' => 'Cloudflare Webhooks',
            'account_id' => 'cloudflare-account-id',
            'api_token' => 'cloudflare-api-token',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testSweegoProfileRequiresWebhookSecretWhenEnabled(): void
    {
        $this->expectExceptionMessage('Webhook secret is required for profile "sweego" when webhooks are enabled.');

        $this->connectionManager()->create([
            'profile' => 'sweego',
            'name' => 'Sweego Webhooks',
            'api_key' => 'sweego-api-key',
            'webhook_enabled' => true,
        ]);
    }

    /**
     * @since 0.1.0
     */
    public function testProviderProfileRejectsUnsupportedDsnOverrideScheme(): void
    {
        $this->expectExceptionMessage('Profile "sendgrid" does not support DSN scheme "smtp".');

        $this->connectionManager()->create([
            'profile' => 'sendgrid',
            'name' => 'Invalid SendGrid Override',
            'dsn' => 'smtp://smtp.example.com',
        ]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @since 0.1.0
     */
    private function assertResolvedTransport(array $input, string $expectedDsn, string $expectedTransportClass): Connection
    {
        $connection = $this->connectionManager()->create($input);
        $dsn = $this->dsnResolver()->resolve($connection);

        self::assertSame($expectedDsn, $dsn);
        self::assertSame($expectedTransportClass, $this->transportRegistry()->create($dsn)::class);

        return $connection;
    }

    /**
     * @since 0.1.0
     */
    private function dsnResolver(): ConnectionDsnResolver
    {
        return $this->container()->get(ConnectionDsnResolver::class);
    }

    /**
     * @since 0.1.0
     */
    private function transportRegistry(): TransportRegistry
    {
        return $this->container()->get(TransportRegistry::class);
    }
}

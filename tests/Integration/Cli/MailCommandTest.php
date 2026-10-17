<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Cli;

use JooosiMail\Admin\Mail\TestEmailDeliveryTemplateListener;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Covers mail WP-CLI command behavior.
 *
 * @since 0.1.0
 */
final class MailCommandTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.12
     */
    public function testSendCommandSubmitsHtmlMailWithRecipientsAndPreferredConnection(): void
    {
        $connection = $this->createNullConnection([
            'name' => 'CLI Send Mail Connection',
        ]);
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        $output = $this->captureCli(function () use ($connection): void {
            $this->mailCommand()->send([], [
                'to' => 'Test Recipient <recipient@example.test>, second@example.test',
                'cc' => 'copy@example.test',
                'bcc' => 'hidden@example.test',
                'reply-to' => 'reply@example.test',
                'subject' => 'CLI Send Subject',
                'message' => '<p>CLI send body</p>',
                'html' => true,
                'connection-id' => $connection->id,
            ]);
        });
        $mailLog = $this->latestRow('mail_logs');
        $payload = json_decode((string) ($mailLog['payload_json'] ?? '{}'), true);
        $plan = json_decode((string) ($mailLog['plan_json'] ?? '{}'), true);

        self::assertStringContainsString('Success: Email #', $output['stdout']);
        self::assertSame('', trim($output['stderr']));
        self::assertIsArray($payload);
        self::assertIsArray($plan);
        self::assertSame('wp_cli', $mailLog['source']);
        self::assertSame('CLI Send Subject', $mailLog['subject']);
        self::assertSame('sent', $mailLog['status']);
        self::assertSame([
            ['address' => 'recipient@example.test', 'name' => 'Test Recipient'],
            ['address' => 'second@example.test', 'name' => null],
        ], $payload['to']);
        self::assertSame('copy@example.test', $payload['cc'][0]['address']);
        self::assertSame('hidden@example.test', $payload['bcc'][0]['address']);
        self::assertSame('reply@example.test', $payload['replyTo'][0]['address']);
        self::assertNull($payload['textBody']);
        self::assertSame('<p>CLI send body</p>', $payload['htmlBody']);
        self::assertSame('text/html; charset=UTF-8', $payload['headers']['Content-Type']);
        self::assertSame((string) $connection->id, $payload['headers']['X-Jooosi-Mail-Connection-Id']);
        self::assertSame($connection->id, (int) $plan['preferredConnectionId']);
    }

    /**
     * @since 0.1.0
     */
    public function testTestCommandSendsDashboardDiagnosticMailAndReportsSuccess(): void
    {
        $this->createNullConnection([
            'name' => 'CLI Default Mail Command Connection',
        ]);
        $targetConnection = $this->createNullConnection([
            'name' => 'CLI Target Mail Command Connection',
            'default' => false,
            'priority' => 20,
        ]);
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        $output = $this->captureCli(function () use ($targetConnection): void {
            $this->mailCommand()->test([], [
                'to' => 'recipient@example.test',
                'subject' => 'CLI Mail Subject',
                'connection-id' => $targetConnection->id,
            ]);
        });
        $mailLog = $this->latestRow('mail_logs');
        $attempt = $this->mailAttemptRepository()->listRecent(limit: 1)[0] ?? null;
        $payload = json_decode((string) ($mailLog['payload_json'] ?? '{}'), true);
        $plan = json_decode((string) ($mailLog['plan_json'] ?? '{}'), true);

        self::assertStringContainsString('Success: The test email was queued or sent successfully.', $output['stdout']);
        self::assertIsArray($mailLog);
        self::assertIsArray($payload);
        self::assertIsArray($plan);
        self::assertSame('admin_test_email', $mailLog['source']);
        self::assertSame('CLI Mail Subject', $mailLog['subject']);
        self::assertSame('sent', $mailLog['status']);
        self::assertSame('admin_test_email', $payload['source']);
        self::assertSame(true, $payload['metadata'][TestEmailDeliveryTemplateListener::METADATA_KEY] ?? null);
        self::assertStringContainsString('Delivery diagnostic: passed', (string) ($payload['textBody'] ?? ''));
        self::assertStringContainsString('Jooosi Mail rendered both HTML', (string) ($payload['htmlBody'] ?? ''));
        self::assertSame('text/html; charset=UTF-8', $payload['headers']['Content-Type'] ?? null);
        self::assertSame((string) $targetConnection->id, $payload['headers']['X-Jooosi-Mail-Connection-Id'] ?? null);
        self::assertSame($targetConnection->id, (int) ($plan['preferredConnectionId'] ?? 0));
        self::assertIsArray($attempt);
        self::assertSame('sent', $attempt['status']);
        self::assertSame($targetConnection->id, (int) $attempt['connection_id']);
    }

    /**
     * @since 0.1.0
     */
    public function testAttemptsListsRecentDeliveryAttempts(): void
    {
        $connection = $this->createNullConnection([
            'name' => 'CLI Attempt Connection',
        ]);
        $this->optionStore()->set('settings.delivery.mode', 'sync');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        wp_mail('recipient@example.test', 'CLI Attempts Subject', 'CLI attempts body');

        $mailLog = $this->latestRow('mail_logs');
        $output = $this->captureCli(function () use ($mailLog, $connection): void {
            $this->mailCommand()->attempts([], [
                'mail-log-id' => (int) ($mailLog['id'] ?? 0),
                'connection-id' => $connection->id,
                'status' => 'sent',
            ]);
        });

        self::assertIsArray($mailLog);
        self::assertStringContainsString((string) $mailLog['id'], $output['stdout']);
        self::assertStringContainsString(sprintf('#%d %s', $connection->id, $connection->name), $output['stdout']);
        self::assertStringContainsString('sent', $output['stdout']);
        self::assertSame('', trim($output['stderr']));
    }
}

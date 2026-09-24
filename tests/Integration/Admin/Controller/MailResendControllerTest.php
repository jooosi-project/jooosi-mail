<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Admin\Controller;

use JooosiMail\Mail\ValueObject\MailRequest;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;
use WP_REST_Request;

/**
 * Covers manual email resubmission from retained mail logs.
 *
 * @since 1.0.9
 */
final class MailResendControllerTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function testAdminCanResendMailFromEveryLifecycleStatus(): void
    {
        $adminUserId = $this->authenticateAdmin();
        $this->registerRoutes();

        foreach (['pending', 'queued', 'processing', 'sent', 'failed'] as $status) {
            $sourceRequest = $this->createResendSourceRequest(sprintf('Manual resend from %s', $status));
            $sourceMailLogId = $this->createMailLog($this->defaultSinglePlan(), $sourceRequest);
            $sourcePayload = $this->mailLogRepository()->find($sourceMailLogId)['payload_json'] ?? null;

            $this->db()->update(
                $this->tableNameResolver()->resolve('mail_logs'),
                ['status' => $status],
                ['id' => $sourceMailLogId],
            );

            $queueCount = $this->countRows('queue_messages');
            $request = new WP_REST_Request(
                'POST',
                sprintf('/jooosi-mail/v1/admin/logs/mail/%d/resend', $sourceMailLogId),
            );
            $response = rest_do_request($request);
            $data = $response->get_data();

            self::assertSame(200, $response->get_status());
            self::assertTrue($data['submitted']);
            self::assertSame($sourceMailLogId, $data['originalMailLogId']);
            self::assertGreaterThan($sourceMailLogId, $data['mailLogId']);
            self::assertSame($queueCount + 1, $this->countRows('queue_messages'));

            $sourceMailLog = $this->mailLogRepository()->find($sourceMailLogId);
            $resendMailLog = $this->mailLogRepository()->find((int) $data['mailLogId']);

            self::assertIsArray($sourceMailLog);
            self::assertIsArray($resendMailLog);
            self::assertSame($status, $sourceMailLog['status']);
            self::assertSame($sourcePayload, $sourceMailLog['payload_json']);
            self::assertSame('queued', $resendMailLog['status']);
            self::assertSame('manual_resend', $resendMailLog['source']);
            self::assertSame($sourceRequest->subject, $resendMailLog['subject']);

            $resendPayload = json_decode((string) $resendMailLog['payload_json'], true);

            self::assertIsArray($resendPayload);
            self::assertSame('keep-me', $resendPayload['headers']['X-Custom']);
            self::assertArrayNotHasKey('Message-ID', $resendPayload['headers']);
            self::assertArrayNotHasKey('X-Schedule-Time', $resendPayload['headers']);
            self::assertSame($sourceMailLogId, $resendPayload['metadata']['manual_resend_of_mail_log_id']);
            self::assertSame($adminUserId, $resendPayload['metadata']['manual_resend_requested_by_user_id']);
            self::assertSame('preserved', $resendPayload['metadata']['existing']);
        }
    }

    /**
     * @since 1.0.9
     */
    public function testResendReturnsNotFoundForMissingMailLog(): void
    {
        $this->authenticateAdmin();
        $this->registerRoutes();

        $request = new WP_REST_Request('POST', '/jooosi-mail/v1/admin/logs/mail/999999/resend');
        $response = rest_do_request($request);

        self::assertSame(404, $response->get_status());
        self::assertSame('jooosi_mail_log_not_found', $response->get_data()['code']);
    }

    /**
     * @since 1.0.9
     */
    public function testResendRejectsMailLogWithoutReusablePayload(): void
    {
        $this->authenticateAdmin();
        $this->registerRoutes();

        $this->db()->insert($this->tableNameResolver()->resolve('mail_logs'), [
            'source' => 'test',
            'subject' => 'Invalid retained payload',
            'recipients_json' => wp_json_encode([['address' => 'recipient@example.com']]),
            'payload_json' => '{invalid',
            'plan_json' => wp_json_encode([]),
            'status' => 'failed',
            'created_at' => '2026-09-19 10:00:00',
            'updated_at' => '2026-09-19 10:00:00',
        ]);

        $mailLogId = (int) $this->db()->lastInsertId();
        $queueCount = $this->countRows('queue_messages');
        $request = new WP_REST_Request(
            'POST',
            sprintf('/jooosi-mail/v1/admin/logs/mail/%d/resend', $mailLogId),
        );
        $response = rest_do_request($request);

        self::assertSame(422, $response->get_status());
        self::assertSame('jooosi_mail_log_payload_unavailable', $response->get_data()['code']);
        self::assertSame($queueCount, $this->countRows('queue_messages'));
    }

    /**
     * @since 1.0.9
     */
    private function createResendSourceRequest(string $subject): MailRequest
    {
        $mailRequest = $this->createMailRequest($subject);

        return new MailRequest(
            from: $mailRequest->from,
            to: $mailRequest->to,
            cc: $mailRequest->cc,
            bcc: $mailRequest->bcc,
            replyTo: $mailRequest->replyTo,
            subject: $mailRequest->subject,
            textBody: $mailRequest->textBody,
            htmlBody: $mailRequest->htmlBody,
            attachments: $mailRequest->attachments,
            headers: [
                'Message-ID' => '<original@example.com>',
                'X-Schedule-Time' => '2099-01-01T00:00:00Z',
                'X-Custom' => 'keep-me',
            ],
            envelopeSender: $mailRequest->envelopeSender,
            source: $mailRequest->source,
            metadata: ['existing' => 'preserved'],
        );
    }

    /**
     * @since 1.0.9
     */
    private function authenticateAdmin(): int
    {
        $userId = $this->factory()->user->create([
            'role' => 'administrator',
        ]);
        wp_set_current_user($userId);

        return $userId;
    }

    /**
     * @since 1.0.9
     */
    private function registerRoutes(): void
    {
        global $wp_rest_server;

        $wp_rest_server = null;
        rest_get_server();
        do_action('rest_api_init');
    }
}

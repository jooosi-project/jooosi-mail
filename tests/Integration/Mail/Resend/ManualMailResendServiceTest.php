<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\Resend;

use JooosiMail\Mail\Resend\ManualMailResendService;
use JooosiMail\Mail\ValueObject\MailAddress;
use JooosiMail\Mail\ValueObject\MailAttachment;
use JooosiMail\Mail\ValueObject\MailRequest;
use JooosiMail\Tests\Integration\Support\JooosiMailIntegrationTestCase;

/**
 * Covers status-independent manual resend behavior at the mail-domain boundary.
 *
 * @since 1.0.9
 */
final class ManualMailResendServiceTest extends JooosiMailIntegrationTestCase
{
    /**
     * @since 1.0.9
     */
    public function testResendPreservesTheSourceAndAllReusableFieldsForEveryStatus(): void
    {
        $this->createNullConnection();
        $this->optionStore()->set('settings.delivery.mode', 'async');
        $this->optionStore()->set('settings.delivery.strategy', 'single');

        $sourceRequest = new MailRequest(
            from: [new MailAddress('sender@example.test', 'Sender')],
            to: [new MailAddress('recipient@example.test', 'Recipient')],
            cc: [new MailAddress('copy@example.test')],
            bcc: [new MailAddress('hidden@example.test')],
            replyTo: [new MailAddress('reply@example.test')],
            subject: 'Reusable subject',
            textBody: 'Reusable text body',
            htmlBody: '<p>Reusable HTML body</p>',
            attachments: [new MailAttachment('/tmp/report.pdf', 'report.pdf', 'application/pdf')],
            headers: [
                'Date' => 'Tue, 01 Jan 2030 00:00:00 +0000',
                'Message-ID' => '<original@example.test>',
                'X-Schedule-Time' => '2030-01-01T00:00:00Z',
                'X-Custom' => 'keep-me',
            ],
            envelopeSender: new MailAddress('bounce@example.test'),
            source: 'integration_source',
            metadata: ['existing' => 'preserve'],
        );
        $manualResendService = $this->container()->get(ManualMailResendService::class);

        foreach (['pending', 'queued', 'processing', 'sent', 'failed'] as $status) {
            $sourceMailLogId = $this->createMailLog($this->defaultSinglePlan(), $sourceRequest);
            $sourceBefore = $this->mailLogRepository()->find($sourceMailLogId);
            $this->db()->update(
                $this->tableNameResolver()->resolve('mail_logs'),
                ['status' => $status],
                ['id' => $sourceMailLogId],
            );

            $result = $manualResendService->resend($sourceMailLogId, 42);
            $sourceAfter = $this->mailLogRepository()->find($sourceMailLogId);
            $resendMailLog = $this->mailLogRepository()->find($result->mailLogId);
            $resendPayload = is_array($resendMailLog)
                ? json_decode((string) ($resendMailLog['payload_json'] ?? ''), true)
                : null;
            $resendRequest = is_array($resendPayload) ? MailRequest::fromArray($resendPayload) : null;

            self::assertTrue($result->accepted);
            self::assertIsArray($sourceBefore);
            self::assertIsArray($sourceAfter);
            self::assertIsArray($resendMailLog);
            self::assertIsArray($resendPayload);
            self::assertInstanceOf(MailRequest::class, $resendRequest);
            self::assertSame($sourceBefore['payload_json'], $sourceAfter['payload_json']);
            self::assertSame($status, $sourceAfter['status']);
            self::assertSame('queued', $resendMailLog['status']);
            self::assertSame('manual_resend', $resendMailLog['source']);
            self::assertEquals($sourceRequest->from, $resendRequest->from);
            self::assertEquals($sourceRequest->to, $resendRequest->to);
            self::assertEquals($sourceRequest->cc, $resendRequest->cc);
            self::assertEquals($sourceRequest->bcc, $resendRequest->bcc);
            self::assertEquals($sourceRequest->replyTo, $resendRequest->replyTo);
            self::assertSame($sourceRequest->subject, $resendRequest->subject);
            self::assertSame($sourceRequest->textBody, $resendRequest->textBody);
            self::assertSame($sourceRequest->htmlBody, $resendRequest->htmlBody);
            self::assertEquals($sourceRequest->attachments, $resendRequest->attachments);
            self::assertEquals($sourceRequest->envelopeSender, $resendRequest->envelopeSender);
            self::assertSame('manual_resend', $resendRequest->source);
            self::assertSame('keep-me', $resendRequest->headers['X-Custom']);
            self::assertArrayNotHasKey('Date', $resendRequest->headers);
            self::assertArrayNotHasKey('Message-ID', $resendRequest->headers);
            self::assertArrayNotHasKey('X-Schedule-Time', $resendRequest->headers);
            self::assertSame('preserve', $resendRequest->metadata['existing']);
            self::assertSame($sourceMailLogId, $resendRequest->metadata['manual_resend_of_mail_log_id']);
            self::assertSame(42, $resendRequest->metadata['manual_resend_requested_by_user_id']);
            self::assertIsString($resendRequest->metadata['manual_resend_requested_at']);
        }
    }
}

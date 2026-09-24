<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Mail\ValueObject;

use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\ValueObject\MailAddress;
use JooosiMail\Mail\ValueObject\MailAttachment;
use JooosiMail\Mail\ValueObject\MailRequest;
use WP_UnitTestCase;

/**
 * Covers immutable copy helpers for mail-domain value objects.
 *
 * @since 1.0.9
 */
final class ImmutableCopyTest extends WP_UnitTestCase
{
    /**
     * @since 1.0.9
     */
    public function testMailRequestWithChangesOnlyRequestedFields(): void
    {
        $attachment = new MailAttachment('/tmp/report.pdf', 'report.pdf', 'application/pdf');
        $original = new MailRequest(
            from: [new MailAddress('sender@example.test', 'Original Sender')],
            to: [new MailAddress('recipient@example.test', 'Recipient')],
            cc: [new MailAddress('copy@example.test')],
            bcc: [new MailAddress('hidden@example.test')],
            replyTo: [new MailAddress('reply@example.test')],
            subject: 'Original subject',
            textBody: 'Plain body',
            htmlBody: '<p>HTML body</p>',
            attachments: [$attachment],
            headers: ['X-Custom' => 'preserve'],
            envelopeSender: new MailAddress('bounce@example.test'),
            source: 'original_source',
            metadata: ['existing' => 'preserve'],
        );
        $replacementFrom = [new MailAddress('replacement@example.test', 'Replacement Sender')];

        $copy = $original->with([
            'from' => $replacementFrom,
            'textBody' => null,
            'envelopeSender' => null,
            'source' => 'replacement_source',
        ]);

        self::assertNotSame($original, $copy);
        self::assertSame($replacementFrom, $copy->from);
        self::assertSame($original->to, $copy->to);
        self::assertSame($original->cc, $copy->cc);
        self::assertSame($original->bcc, $copy->bcc);
        self::assertSame($original->replyTo, $copy->replyTo);
        self::assertSame($original->subject, $copy->subject);
        self::assertNull($copy->textBody);
        self::assertSame($original->htmlBody, $copy->htmlBody);
        self::assertSame($original->attachments, $copy->attachments);
        self::assertSame($original->headers, $copy->headers);
        self::assertNull($copy->envelopeSender);
        self::assertSame('replacement_source', $copy->source);
        self::assertSame($original->metadata, $copy->metadata);
        self::assertSame('Plain body', $original->textBody);
        self::assertSame('original_source', $original->source);
    }

    /**
     * @since 1.0.9
     */
    public function testMailRequestCopyAliasPreservesTheWirePayload(): void
    {
        $original = new MailRequest(
            from: [new MailAddress('sender@example.test')],
            to: [new MailAddress('recipient@example.test')],
            cc: [],
            bcc: [],
            replyTo: [],
            subject: 'Original subject',
            textBody: null,
            htmlBody: 'HTML body',
            attachments: [],
            headers: ['X-Custom' => 'preserve'],
            metadata: ['existing' => true],
        );

        $copy = $original->copy(['subject' => 'Copied subject']);

        self::assertSame('Copied subject', $copy->subject);
        self::assertSame($original->toArray()['from'], $copy->toArray()['from']);
        self::assertSame($original->toArray()['to'], $copy->toArray()['to']);
        self::assertSame($original->toArray()['headers'], $copy->toArray()['headers']);
        self::assertSame($original->toArray()['metadata'], $copy->toArray()['metadata']);
    }

    /**
     * @since 1.0.9
     */
    public function testConnectionWithAndCopyPreserveUnchangedFields(): void
    {
        $original = new Connection(
            id: 123,
            profileKey: 'smtp',
            name: 'Primary SMTP',
            dsn: 'smtp://mailer%40example.test:secret@smtp.example.test:587',
            settings: [
                'profile' => ['scheme' => 'smtp', 'host' => 'smtp.example.test'],
                'rate_limits' => ['minute' => 10],
            ],
            secrets: [
                'profile' => ['password' => 'secret'],
                'webhook_secret' => 'webhook-secret',
            ],
            enabled: true,
            default: false,
            priority: 3,
            weight: 7,
            webhookEnabled: true,
        );

        $disabled = $original->with(['enabled' => false]);

        self::assertNotSame($original, $disabled);
        self::assertSame($original->id, $disabled->id);
        self::assertSame($original->profileKey, $disabled->profileKey);
        self::assertSame($original->name, $disabled->name);
        self::assertSame($original->dsn, $disabled->dsn);
        self::assertSame($original->settings, $disabled->settings);
        self::assertSame($original->secrets, $disabled->secrets);
        self::assertFalse($disabled->enabled);
        self::assertSame($original->default, $disabled->default);
        self::assertSame($original->priority, $disabled->priority);
        self::assertSame($original->weight, $disabled->weight);
        self::assertSame($original->webhookEnabled, $disabled->webhookEnabled);

        $copied = $original->copy(['priority' => 20]);

        self::assertSame(20, $copied->priority);
        self::assertSame($original->enabled, $copied->enabled);
        self::assertSame($original->default, $copied->default);
        self::assertSame($original->settings, $copied->settings);
        self::assertSame($original->secrets, $copied->secrets);
    }
}

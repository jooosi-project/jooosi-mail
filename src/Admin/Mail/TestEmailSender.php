<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Mail;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\ValueObject\MailRequest;

/**
 * Sends generated Jooosi Mail diagnostic test messages.
 *
 * @since 0.1.0
 */
#[Service]
final class TestEmailSender
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly TestEmailTemplateRenderer $templateRenderer,
    ) {
    }

    /**
     * Send a generated diagnostic test email through `wp_mail()`.
     *
     * @since 0.1.0
     */
    public function send(string $to, ?string $subject = null, ?int $connectionId = null): bool
    {
        $subject = sanitize_text_field((string) ($subject ?? ''));

        if ($subject === '') {
            $subject = __('Jooosi Mail test', 'jooosi-mail');
        }

        $bodies = $this->templateRenderer->render(
            recipientSummary: $to,
            connectionLabel: __('Not routed by Jooosi Mail', 'jooosi-mail'),
            connectionProfile: __('WordPress native wp_mail()', 'jooosi-mail'),
            deliveryMode: __('Native wp_mail', 'jooosi-mail'),
            routingStrategy: __('None', 'jooosi-mail'),
            mailLogLabel: __('Not logged by Jooosi Mail', 'jooosi-mail'),
            mailLogUrl: admin_url('admin.php?page=jooosi-mail#/logs/mail'),
        );
        $markTestMailRequest = static fn (MailRequest $mailRequest): MailRequest => new MailRequest(
            from: $mailRequest->from,
            to: $mailRequest->to,
            cc: $mailRequest->cc,
            bcc: $mailRequest->bcc,
            replyTo: $mailRequest->replyTo,
            subject: $mailRequest->subject,
            textBody: $mailRequest->textBody,
            htmlBody: $mailRequest->htmlBody,
            attachments: $mailRequest->attachments,
            headers: $mailRequest->headers,
            envelopeSender: $mailRequest->envelopeSender,
            source: 'admin_test_email',
            metadata: array_merge($mailRequest->metadata, [TestEmailDeliveryTemplateListener::METADATA_KEY => true]),
        );
        $setAltBody = static function (object $phpmailer) use ($bodies): void {
            if (property_exists($phpmailer, 'AltBody')) {
                $phpmailer->AltBody = $bodies['textBody'];
            }
        };

        add_filter('f!jooosi-mail/mail:normalize.request', $markTestMailRequest, 10, 1);
        add_action('phpmailer_init', $setAltBody);

        try {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $connectionId = max(0, (int) ($connectionId ?? 0));

            if ($connectionId > 0) {
                $headers[] = sprintf('X-Jooosi-Mail-Connection-Id: %d', $connectionId);
            }

            return wp_mail($to, $subject, $bodies['htmlBody'], $headers);
        } finally {
            remove_filter('f!jooosi-mail/mail:normalize.request', $markTestMailRequest, 10);
            remove_action('phpmailer_init', $setAltBody);
        }
    }
}

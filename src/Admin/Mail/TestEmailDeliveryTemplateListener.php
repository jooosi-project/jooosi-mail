<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Mail;

use JooosiMail\Discovery\Attribute\Hook;
use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\Routing\DeliveryPlan;
use JooosiMail\Mail\ValueObject\MailAddress;
use JooosiMail\Mail\ValueObject\MailRequest;
/**
 * Applies generated test email content when a test message is delivered.
 *
 * @since 0.1.0
 */
#[Service]
final class TestEmailDeliveryTemplateListener
{
    /**
     * @var string
     */
    public const METADATA_KEY = 'jooosi_mail_test_email';
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly \JooosiMail\Admin\Mail\TestEmailTemplateRenderer $templateRenderer)
    {
    }
    /**
     * @since 0.1.0
     */
    #[Hook(name: 'f!jooosi-mail/mail:delivery.request', kind: 'filter', acceptedArgs: 4)]
    public function applyTemplate(MailRequest $mailRequest, int $mailLogId, Connection $connection, DeliveryPlan $deliveryPlan): MailRequest
    {
        if (($mailRequest->metadata[self::METADATA_KEY] ?? \false) !== \true) {
            return $mailRequest;
        }
        $bodies = $this->templateRenderer->render(recipientSummary: $this->formatMailAddresses($mailRequest->to), connectionLabel: sprintf('#%d %s', (int) $connection->id, $connection->name), connectionProfile: $this->formatMachineLabel($connection->profileKey), deliveryMode: $this->formatMachineLabel($deliveryPlan->mode->value), routingStrategy: $this->formatMachineLabel($deliveryPlan->strategy->value), mailLogLabel: sprintf('#%d', $mailLogId), mailLogUrl: admin_url(sprintf('admin.php?page=jooosi-mail#/logs/mail?id=%d', $mailLogId)));
        return new MailRequest(from: $mailRequest->from, to: $mailRequest->to, cc: $mailRequest->cc, bcc: $mailRequest->bcc, replyTo: $mailRequest->replyTo, subject: $mailRequest->subject, textBody: $bodies['textBody'], htmlBody: $bodies['htmlBody'], attachments: $mailRequest->attachments, headers: $mailRequest->headers, envelopeSender: $mailRequest->envelopeSender, source: $mailRequest->source, metadata: array_merge($mailRequest->metadata, ['jooosi_mail_test_connection_id' => $connection->id, 'jooosi_mail_test_connection_name' => $connection->name, 'jooosi_mail_test_connection_profile' => $connection->profileKey]));
    }
    /**
     * @param list<MailAddress> $addresses
     *
     * @since 0.1.0
     */
    private function formatMailAddresses(array $addresses): string
    {
        if ($addresses === []) {
            return '-';
        }
        return implode(', ', array_map(static function (MailAddress $address): string {
            if ($address->name === null || $address->name === '') {
                return $address->address;
            }
            return sprintf('%s <%s>', $address->name, $address->address);
        }, $addresses));
    }
    /**
     * @since 0.1.0
     */
    private function formatMachineLabel(string $value): string
    {
        $label = trim(str_replace(['_', '-'], ' ', $value));
        return $label !== '' ? ucwords($label) : '-';
    }
}

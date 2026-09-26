<?php

declare (strict_types=1);
namespace JooosiMail\Mail\Delivery;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Mail\ValueObject\MailAddress;
use JooosiMail\Mail\ValueObject\MailAttachment;
use JooosiMail\Mail\ValueObject\MailRequest;
use JooosiMailDeps\Symfony\Component\Mailer\Envelope;
use JooosiMailDeps\Symfony\Component\Mime\Email;
/**
 * Builds Symfony email objects from normalized payloads.
 *
 * @since 0.1.0
 */
#[Service]
final class EmailFactory
{
    /**
     * @since 0.1.0
     */
    public function create(MailRequest $mailRequest): Email
    {
        $email = new Email();
        $this->applyAddresses($email, 'from', $mailRequest->from);
        $this->applyAddresses($email, 'to', $mailRequest->to);
        $this->applyAddresses($email, 'cc', $mailRequest->cc);
        $this->applyAddresses($email, 'bcc', $mailRequest->bcc);
        $this->applyAddresses($email, 'replyTo', $mailRequest->replyTo);
        $email->subject($mailRequest->subject);
        if ($mailRequest->textBody !== null) {
            $email->text($mailRequest->textBody);
        }
        if ($mailRequest->htmlBody !== null) {
            $email->html($mailRequest->htmlBody);
        }
        foreach ($mailRequest->headers as $name => $value) {
            if ($this->isBodyHeader($name)) {
                continue;
            }
            if ($email->getHeaders()->has($name)) {
                continue;
            }
            $email->getHeaders()->addTextHeader($name, (string) $value);
        }
        foreach ($mailRequest->attachments as $attachment) {
            if ($attachment instanceof MailAttachment && is_file($attachment->path)) {
                $email->attachFromPath($attachment->path, $attachment->name, $attachment->contentType);
            }
        }
        return $email;
    }
    /**
     * @since 0.1.0
     */
    public function createEnvelope(MailRequest $mailRequest): ?Envelope
    {
        if (!$mailRequest->envelopeSender instanceof MailAddress) {
            return null;
        }
        $recipients = array_merge($mailRequest->to, $mailRequest->cc, $mailRequest->bcc);
        if ($recipients === []) {
            return null;
        }
        return new Envelope($mailRequest->envelopeSender->toSymfony(), array_map(static fn(MailAddress $address) => $address->toSymfony(), $recipients));
    }
    /**
     * @since 0.1.0
     */
    private function isBodyHeader(string $name): bool
    {
        return in_array(strtolower($name), ['content-type', 'content-transfer-encoding'], \true);
    }
    /**
     * @param list<MailAddress> $addresses
     *
     * @since 0.1.0
     */
    private function applyAddresses(Email $email, string $method, array $addresses): void
    {
        if ($addresses === []) {
            return;
        }
        $email->{$method}(...array_map(static fn(MailAddress $address) => $address->toSymfony(), $addresses));
    }
}

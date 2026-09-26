<?php

declare (strict_types=1);
namespace JooosiMail\Mail\ValueObject;

/**
 * Stable normalized email payload used across sync and async flows.
 *
 * @since 0.1.0
 */
final class MailRequest
{
    /**
     * @param list<MailAddress> $from
     * @param list<MailAddress> $to
     * @param list<MailAddress> $cc
     * @param list<MailAddress> $bcc
     * @param list<MailAddress> $replyTo
     * @param list<MailAttachment> $attachments
     * @param array<string, string> $headers
     * @param array<string, mixed>  $metadata
     */
    public function __construct(public readonly array $from, public readonly array $to, public readonly array $cc, public readonly array $bcc, public readonly array $replyTo, public readonly string $subject, public readonly ?string $textBody, public readonly ?string $htmlBody, public readonly array $attachments, public readonly array $headers, public readonly ?\JooosiMail\Mail\ValueObject\MailAddress $envelopeSender = null, public readonly string $source = 'wp_mail', public readonly array $metadata = [])
    {
    }
    /**
     * Create an immutable copy with only the supplied fields changed.
     *
     * Array keys are checked explicitly so nullable fields can be cleared by
     * passing null without changing the existing constructor contract.
     *
     * @param array{
     *     from?: list<MailAddress>,
     *     to?: list<MailAddress>,
     *     cc?: list<MailAddress>,
     *     bcc?: list<MailAddress>,
     *     replyTo?: list<MailAddress>,
     *     subject?: string,
     *     textBody?: string|null,
     *     htmlBody?: string|null,
     *     attachments?: list<MailAttachment>,
     *     headers?: array<string, string>,
     *     envelopeSender?: MailAddress|null,
     *     source?: string,
     *     metadata?: array<string, mixed>
     * } $changes
     *
     * @since 1.0.9
     */
    public function with(array $changes): self
    {
        return new self(from: array_key_exists('from', $changes) ? $changes['from'] : $this->from, to: array_key_exists('to', $changes) ? $changes['to'] : $this->to, cc: array_key_exists('cc', $changes) ? $changes['cc'] : $this->cc, bcc: array_key_exists('bcc', $changes) ? $changes['bcc'] : $this->bcc, replyTo: array_key_exists('replyTo', $changes) ? $changes['replyTo'] : $this->replyTo, subject: array_key_exists('subject', $changes) ? $changes['subject'] : $this->subject, textBody: array_key_exists('textBody', $changes) ? $changes['textBody'] : $this->textBody, htmlBody: array_key_exists('htmlBody', $changes) ? $changes['htmlBody'] : $this->htmlBody, attachments: array_key_exists('attachments', $changes) ? $changes['attachments'] : $this->attachments, headers: array_key_exists('headers', $changes) ? $changes['headers'] : $this->headers, envelopeSender: array_key_exists('envelopeSender', $changes) ? $changes['envelopeSender'] : $this->envelopeSender, source: array_key_exists('source', $changes) ? $changes['source'] : $this->source, metadata: array_key_exists('metadata', $changes) ? $changes['metadata'] : $this->metadata);
    }
    /**
     * Alias for callers that prefer copy semantics.
     *
     * @param array{
     *     from?: list<MailAddress>,
     *     to?: list<MailAddress>,
     *     cc?: list<MailAddress>,
     *     bcc?: list<MailAddress>,
     *     replyTo?: list<MailAddress>,
     *     subject?: string,
     *     textBody?: string|null,
     *     htmlBody?: string|null,
     *     attachments?: list<MailAttachment>,
     *     headers?: array<string, string>,
     *     envelopeSender?: MailAddress|null,
     *     source?: string,
     *     metadata?: array<string, mixed>
     * } $changes
     *
     * @since 1.0.9
     */
    public function copy(array $changes = []): self
    {
        return $this->with($changes);
    }
    /**
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        return new self(from: array_map(static fn(array $item): \JooosiMail\Mail\ValueObject\MailAddress => \JooosiMail\Mail\ValueObject\MailAddress::fromArray($item), $data['from'] ?? []), to: array_map(static fn(array $item): \JooosiMail\Mail\ValueObject\MailAddress => \JooosiMail\Mail\ValueObject\MailAddress::fromArray($item), $data['to'] ?? []), cc: array_map(static fn(array $item): \JooosiMail\Mail\ValueObject\MailAddress => \JooosiMail\Mail\ValueObject\MailAddress::fromArray($item), $data['cc'] ?? []), bcc: array_map(static fn(array $item): \JooosiMail\Mail\ValueObject\MailAddress => \JooosiMail\Mail\ValueObject\MailAddress::fromArray($item), $data['bcc'] ?? []), replyTo: array_map(static fn(array $item): \JooosiMail\Mail\ValueObject\MailAddress => \JooosiMail\Mail\ValueObject\MailAddress::fromArray($item), $data['replyTo'] ?? []), subject: (string) ($data['subject'] ?? ''), textBody: isset($data['textBody']) ? (string) $data['textBody'] : null, htmlBody: isset($data['htmlBody']) ? (string) $data['htmlBody'] : null, attachments: array_map(static fn(array $item): \JooosiMail\Mail\ValueObject\MailAttachment => \JooosiMail\Mail\ValueObject\MailAttachment::fromArray($item), $data['attachments'] ?? []), headers: $data['headers'] ?? [], envelopeSender: is_array($data['envelopeSender'] ?? null) ? \JooosiMail\Mail\ValueObject\MailAddress::fromArray($data['envelopeSender']) : null, source: (string) ($data['source'] ?? 'wp_mail'), metadata: $data['metadata'] ?? []);
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return ['from' => array_map(static fn(\JooosiMail\Mail\ValueObject\MailAddress $item): array => $item->toArray(), $this->from), 'to' => array_map(static fn(\JooosiMail\Mail\ValueObject\MailAddress $item): array => $item->toArray(), $this->to), 'cc' => array_map(static fn(\JooosiMail\Mail\ValueObject\MailAddress $item): array => $item->toArray(), $this->cc), 'bcc' => array_map(static fn(\JooosiMail\Mail\ValueObject\MailAddress $item): array => $item->toArray(), $this->bcc), 'replyTo' => array_map(static fn(\JooosiMail\Mail\ValueObject\MailAddress $item): array => $item->toArray(), $this->replyTo), 'subject' => $this->subject, 'textBody' => $this->textBody, 'htmlBody' => $this->htmlBody, 'attachments' => array_map(static fn(\JooosiMail\Mail\ValueObject\MailAttachment $item): array => $item->toArray(), $this->attachments), 'headers' => $this->headers, 'envelopeSender' => $this->envelopeSender?->toArray(), 'source' => $this->source, 'metadata' => $this->metadata];
    }
}

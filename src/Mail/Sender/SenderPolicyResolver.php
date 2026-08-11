<?php

declare(strict_types=1);

namespace JooosiMail\Mail\Sender;

use JooosiMail\Discovery\Attribute\Service;
use JooosiMail\Infrastructure\WordPress\OptionStore;
use JooosiMail\Mail\Connection\Connection;
use JooosiMail\Mail\ValueObject\MailAddress;
use JooosiMail\Mail\ValueObject\MailRequest;

/**
 * Resolves the effective sender identity for a selected delivery connection.
 *
 * @since 0.1.0
 */
#[Service]
final class SenderPolicyResolver
{
    /**
     * @var string
     */
    public const RETURN_PATH_MODE_INHERIT = 'inherit';
    /**
     * @var string
     */
    public const RETURN_PATH_MODE_PROVIDER_DEFAULT = 'provider_default';
    /**
     * @var string
     */
    public const RETURN_PATH_MODE_MATCH_FROM = 'match_from';
    /**
     * @var string
     */
    public const RETURN_PATH_MODE_CUSTOM = 'custom';

    /**
     * @var string
     */
    private const DEFAULT_FROM_APPLIED_METADATA_KEY = 'jooosi_mail_default_from_applied';

    public function __construct(
        private readonly OptionStore $optionStore,
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function apply(MailRequest $mailRequest, Connection $connection): MailRequest
    {
        $globalPolicy = $this->getGlobalPolicy();
        $connectionPolicy = $this->getConnectionPolicy($connection);
        $from = $this->resolveFrom($mailRequest, $connectionPolicy, $globalPolicy);
        $envelopeSender = $this->resolveEnvelopeSender($mailRequest, $from, $connectionPolicy, $globalPolicy);

        if ($this->addressesMatch($mailRequest->from, $from) && $this->addressMatches($mailRequest->envelopeSender, $envelopeSender)) {
            return $mailRequest;
        }

        return new MailRequest(
            from: $from,
            to: $mailRequest->to,
            cc: $mailRequest->cc,
            bcc: $mailRequest->bcc,
            replyTo: $mailRequest->replyTo,
            subject: $mailRequest->subject,
            textBody: $mailRequest->textBody,
            htmlBody: $mailRequest->htmlBody,
            attachments: $mailRequest->attachments,
            headers: $mailRequest->headers,
            envelopeSender: $envelopeSender,
            source: $mailRequest->source,
            metadata: $mailRequest->metadata,
        );
    }

    /**
     * @param array<string, mixed> $connectionPolicy
     * @param array<string, mixed> $globalPolicy
     * @return list<MailAddress>
     *
     * @since 0.1.0
     */
    private function resolveFrom(MailRequest $mailRequest, array $connectionPolicy, array $globalPolicy): array
    {
        $forceEmail = $this->resolveForceEnabled($connectionPolicy, $globalPolicy, 'force_email');
        $forceName = $this->resolveForceEnabled($connectionPolicy, $globalPolicy, 'force_name');
        $defaultFromApplied = ($mailRequest->metadata[self::DEFAULT_FROM_APPLIED_METADATA_KEY] ?? false) === true;

        if (! $forceEmail && ! $forceName && ! $defaultFromApplied && $mailRequest->from !== []) {
            return $mailRequest->from;
        }

        $originalFrom = $mailRequest->from[0] ?? null;
        $configuredEmail = $this->extractEmail($connectionPolicy['email'] ?? null)
            ?? $this->extractEmail($globalPolicy['email'] ?? null);
        $configuredName = $this->extractString($connectionPolicy['name'] ?? null)
            ?? $this->extractString($globalPolicy['name'] ?? null);
        $fallbackEmail = $this->extractEmail(get_option('admin_email')) ?? $originalFrom?->address ?? '';
        $fallbackName = $this->extractString(get_option('blogname'));
        $email = match (true) {
            $forceEmail => $configuredEmail ?? $originalFrom?->address ?? $fallbackEmail,
            ! $defaultFromApplied && $originalFrom instanceof MailAddress => $originalFrom->address,
            default => $configuredEmail ?? $fallbackEmail,
        };
        $name = match (true) {
            $forceName => $configuredName ?? $originalFrom?->name ?? $fallbackName,
            ! $defaultFromApplied && $originalFrom instanceof MailAddress => $originalFrom->name,
            default => $configuredName ?? $fallbackName,
        };

        return [new MailAddress($email, $name)];
    }

    /**
     * @param list<MailAddress>    $from
     * @param array<string, mixed> $connectionPolicy
     * @param array<string, mixed> $globalPolicy
     *
     * @since 0.1.0
     */
    private function resolveEnvelopeSender(
        MailRequest $mailRequest,
        array $from,
        array $connectionPolicy,
        array $globalPolicy,
    ): ?MailAddress {
        $connectionMode = $this->normalizeConnectionReturnPathMode($connectionPolicy['return_path_mode'] ?? null);
        $mode = $connectionMode ?? $this->normalizeGlobalReturnPathMode($globalPolicy['return_path_mode'] ?? null);

        if ($mode === self::RETURN_PATH_MODE_MATCH_FROM) {
            $fromAddress = $from[0] ?? null;

            return $fromAddress instanceof MailAddress ? new MailAddress($fromAddress->address) : null;
        }

        if ($mode === self::RETURN_PATH_MODE_CUSTOM) {
            $email = $connectionMode === self::RETURN_PATH_MODE_CUSTOM
                ? $this->extractEmail($connectionPolicy['return_path_email'] ?? null)
                : $this->extractEmail($globalPolicy['return_path_email'] ?? null);

            return $email !== null ? new MailAddress($email) : null;
        }

        return $mailRequest->envelopeSender;
    }

    /**
     * @param list<MailAddress> $left
     * @param list<MailAddress> $right
     *
     * @since 0.1.0
     */
    private function addressesMatch(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        foreach ($left as $index => $leftAddress) {
            if (! $this->addressMatches($leftAddress, $right[$index] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @since 0.1.0
     */
    private function addressMatches(?MailAddress $left, ?MailAddress $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left->address === $right->address && $left->name === $right->name;
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function getGlobalPolicy(): array
    {
        return [
            'email' => $this->optionStore->get('settings.mail.sender.email'),
            'name' => $this->optionStore->get('settings.mail.sender.name'),
            'force_email' => (bool) $this->optionStore->get('settings.mail.sender.force_email', false),
            'force_name' => (bool) $this->optionStore->get('settings.mail.sender.force_name', false),
            'return_path_mode' => $this->optionStore->get('settings.mail.sender.return_path_mode', self::RETURN_PATH_MODE_PROVIDER_DEFAULT),
            'return_path_email' => $this->optionStore->get('settings.mail.sender.return_path_email'),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function getConnectionPolicy(Connection $connection): array
    {
        $policy = $connection->settings['sender'] ?? null;

        return is_array($policy) ? $policy : [];
    }

    /**
     * @param array<string, mixed> $connectionPolicy
     * @param array<string, mixed> $globalPolicy
     *
     * @since 0.1.0
     */
    private function resolveForceEnabled(array $connectionPolicy, array $globalPolicy, string $key): bool
    {
        if ($this->extractBool($connectionPolicy[$key] ?? null)) {
            return true;
        }

        return (bool) ($globalPolicy[$key] ?? false);
    }

    /**
     * @since 0.1.0
     */
    private function extractBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'force'], true);
    }

    /**
     * @since 0.1.0
     */
    private function normalizeConnectionReturnPathMode(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $mode = strtolower(trim((string) $value));

        return in_array($mode, [
            self::RETURN_PATH_MODE_PROVIDER_DEFAULT,
            self::RETURN_PATH_MODE_MATCH_FROM,
            self::RETURN_PATH_MODE_CUSTOM,
        ], true) ? $mode : null;
    }

    /**
     * @since 0.1.0
     */
    private function normalizeGlobalReturnPathMode(mixed $value): string
    {
        if (! is_scalar($value)) {
            return self::RETURN_PATH_MODE_PROVIDER_DEFAULT;
        }

        $mode = strtolower(trim((string) $value));

        return in_array($mode, [
            self::RETURN_PATH_MODE_PROVIDER_DEFAULT,
            self::RETURN_PATH_MODE_MATCH_FROM,
            self::RETURN_PATH_MODE_CUSTOM,
        ], true) ? $mode : self::RETURN_PATH_MODE_PROVIDER_DEFAULT;
    }

    /**
     * @since 0.1.0
     */
    private function extractEmail(mixed $value): ?string
    {
        $email = $this->extractString($value);

        if ($email === null) {
            return null;
        }

        return is_email($email) ? $email : null;
    }

    /**
     * @since 0.1.0
     */
    private function extractString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}

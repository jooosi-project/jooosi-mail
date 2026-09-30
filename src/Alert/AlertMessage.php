<?php

declare(strict_types=1);

namespace JooosiMail\Alert;

/**
 * Common failure or test alert content passed to channel drivers.
 *
 * @property-read string      $title
 * @property-read string      $siteName
 * @property-read string|null $subject
 * @property-read string|null $error
 * @property-read int|null    $mailLogId
 * @property-read string|null $logUrl
 * @property-read bool        $isTest
 *
 * @since 1.0.12
 */
final class AlertMessage
{
    /**
     * @param string      $title
     * @param string      $siteName
     * @param string|null $subject
     * @param string|null $error
     * @param int|null    $mailLogId
     * @param string|null $logUrl
     * @param bool        $isTest
     *
     * @since 1.0.12
     */
    public function __construct(
        public readonly string $title,
        public readonly string $siteName,
        public readonly ?string $subject = null,
        public readonly ?string $error = null,
        public readonly ?int $mailLogId = null,
        public readonly ?string $logUrl = null,
        public readonly bool $isTest = false,
    ) {
    }

    /**
     * Render the alert as plain text for channels that do not support rich formatting.
     *
     * @since 1.0.12
     */
    public function toPlainText(): string
    {
        $lines = [$this->title, 'Site: ' . $this->siteName];

        if ($this->subject !== null) {
            $lines[] = 'Subject: ' . $this->subject;
        }

        if ($this->error !== null) {
            $lines[] = 'Error: ' . $this->error;
        }

        if ($this->mailLogId !== null) {
            $lines[] = 'Mail log: #' . $this->mailLogId;
        }

        if ($this->logUrl !== null) {
            $lines[] = $this->logUrl;
        }

        return implode("\n", $lines);
    }
}

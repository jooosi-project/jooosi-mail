<?php

declare (strict_types=1);
namespace JooosiMail\Queue\Worker;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Provides a stable label for the current queue worker process.
 *
 * @since 1.0.9
 */
#[Service]
final class QueueWorkerIdentity
{
    /** @var string Stable hostname and process label for this worker. */
    private readonly string $id;
    public function __construct()
    {
        $hostname = gethostname();
        $hostname = is_string($hostname) && trim($hostname) !== '' ? $hostname : \PHP_SAPI;
        $hostname = sanitize_text_field($hostname);
        $hostname = $hostname !== '' ? $hostname : \PHP_SAPI;
        $processId = getmypid();
        $this->id = sprintf('%s:%s', substr($hostname, 0, 140), is_int($processId) ? (string) $processId : 'unknown');
    }
    /**
     * @since 1.0.9
     */
    public function id(): string
    {
        return $this->id;
    }
}

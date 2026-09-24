<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\Container;

use RuntimeException;

/**
 * Serializes container cache mutations with a re-entrant process lock.
 *
 * @since 1.0.9
 */
final class ContainerCacheBuildLock
{
    /**
     * @var resource|null
     */
    private mixed $lock = null;

    public function __construct(
        private readonly ContainerCacheArtifactStore $artifactStore,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     * @return T
     *
     * @since 1.0.9
     */
    public function run(callable $callback): mixed
    {
        if (is_resource($this->lock)) {
            return $callback();
        }

        $this->artifactStore->ensureDirectoryExists();
        $lockPath = $this->artifactStore->buildLockFile();
        $lock = fopen($lockPath, 'c');

        if (! is_resource($lock)) {
            throw new RuntimeException(sprintf('Unable to open the Jooosi Mail container cache build lock at "%s".', $lockPath));
        }

        $locked = false;

        try {
            $locked = flock($lock, LOCK_EX);

            if (! $locked) {
                throw new RuntimeException(sprintf('Unable to acquire the Jooosi Mail container cache build lock at "%s".', $lockPath));
            }

            $this->lock = $lock;

            return $callback();
        } finally {
            $this->lock = null;

            if ($locked) {
                flock($lock, LOCK_UN);
            }

            fclose($lock);
        }
    }
}

<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\Container;

use JooosiMail\Bootstrap\Paths;
use RuntimeException;
use Throwable;

/**
 * Persists and validates compiled container cache artifacts.
 *
 * @since 1.0.9
 */
final class ContainerCacheArtifactStore
{
    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    /**
     * @since 1.0.9
     */
    public function cacheFile(): string
    {
        return $this->paths->cacheDir . '/container.php';
    }

    /**
     * @since 1.0.9
     */
    public function metadataFile(): string
    {
        return $this->paths->cacheDir . '/container.meta.php';
    }

    /**
     * @since 1.0.9
     */
    public function buildLockFile(): string
    {
        return $this->paths->cacheDir . '/container.build.lock';
    }

    /**
     * @return array<string, mixed>|null
     *
     * @since 1.0.9
     */
    public function readMetadata(): ?array
    {
        $metadataFile = $this->metadataFile();

        if (! is_file($metadataFile)) {
            return null;
        }

        try {
            $metadata = require $metadataFile;
        } catch (Throwable) {
            return null;
        }

        return is_array($metadata) ? $metadata : null;
    }

    /**
     * @since 1.0.9
     */
    public function containsClass(string $cacheFile, string $className): bool
    {
        if (! is_readable($cacheFile)) {
            return false;
        }

        $contents = file_get_contents($cacheFile);

        if (! is_string($contents)) {
            return false;
        }

        $className = ltrim($className, '\\');
        $separatorPosition = strrpos($className, '\\');
        $shortClassName = $separatorPosition === false ? $className : substr($className, $separatorPosition + 1);

        return preg_match('/\\bclass\\s+' . preg_quote($shortClassName, '/') . '\\b/', $contents) === 1;
    }

    /**
     * @since 1.0.9
     */
    public function ensureDirectoryExists(): void
    {
        if (! is_dir($this->paths->cacheDir)) {
            wp_mkdir_p($this->paths->cacheDir);
        }

        if (! is_dir($this->paths->cacheDir)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('The Jooosi Mail cache directory "%s" could not be created.', $this->paths->cacheDir));
        }
    }

    /**
     * @since 1.0.9
     */
    public function writePhpFile(string $path, string $contents): void
    {
        $temporaryFile = tempnam($this->paths->cacheDir, 'jooosi-mail-');

        if (! is_string($temporaryFile) || $temporaryFile === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to allocate a temporary file for "%s".', $path));
        }

        $bytesWritten = file_put_contents($temporaryFile, $contents, LOCK_EX);

        if ($bytesWritten === false || $bytesWritten !== strlen($contents)) {
            $this->deleteFile($temporaryFile);

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to write the Jooosi Mail cache file "%s".', $path));
        }

        $permissions = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;

        if (! chmod($temporaryFile, $permissions)) {
            $this->deleteFile($temporaryFile);

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to set permissions on the Jooosi Mail cache file "%s".', $path));
        }

        if (! rename($temporaryFile, $path)) {
            $this->deleteFile($temporaryFile);

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to move the Jooosi Mail cache file into place at "%s".', $path));
        }

        $this->invalidateOpcodeCache($path);
    }

    /**
     * @since 1.0.9
     */
    public function deleteFile(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $this->invalidateOpcodeCache($path);

        wp_delete_file($path);
    }

    /**
     * @since 1.0.9
     */
    private function invalidateOpcodeCache(string $path): void
    {
        clearstatcache(true, $path);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}

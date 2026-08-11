<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\Container;

use JooosiMail\Bootstrap\Environment;
use JooosiMail\Bootstrap\Paths;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Throwable;

/**
 * Loads and dumps the compiled Symfony container.
 *
 * @since 0.1.0
 */
final class ContainerCache
{
    public function __construct(
        private readonly Paths $paths,
        private readonly Environment $environment,
    ) {
    }

    /**
     * Determine whether a cached container may be reused.
     *
     * @since 0.1.0
     */
    public function isUsable(): bool
    {
        return $this->inspect()['usable'];
    }

    /**
     * @return array{
     *     usable: bool,
     *     reasons: list<string>,
     *     environment: string,
     *     debug: bool,
     *     cache_file: string,
     *     cache_file_exists: bool,
     *     metadata_file: string,
     *     metadata_file_exists: bool,
     *     generated_at: ?string,
     *     tracked_file_count: int,
     *     current_source_hash: string,
     *     cached_source_hash: ?string,
     *     expected_container_class: string,
     *     cached_container_class: ?string
     * }
     *
     * @since 0.1.0
     */
    public function inspect(): array
    {
        $signature = $this->buildSignature();
        $cacheFile = $this->getCacheFile();
        $metadataFile = $this->getMetadataFile();
        $metadata = $this->readMetadata();
        $reasons = [];

        if ($this->environment->debug) {
            $reasons[] = 'debug_mode';
        }

        if (! is_file($cacheFile)) {
            $reasons[] = 'missing_cache_file';
        }

        if (! is_file($metadataFile)) {
            $reasons[] = 'missing_metadata_file';
        } elseif ($metadata === null) {
            $reasons[] = 'invalid_metadata_file';
        }

        if (is_array($metadata)) {
            if (($metadata['environment'] ?? null) !== $this->environment->name) {
                $reasons[] = 'environment_mismatch';
            }

            if (($metadata['debug'] ?? null) !== $this->environment->debug) {
                $reasons[] = 'debug_flag_mismatch';
            }

            if (($metadata['source_hash'] ?? null) !== $signature['source_hash']) {
                $reasons[] = 'source_hash_mismatch';
            }

            if (($metadata['container_class'] ?? null) !== $signature['container_class']) {
                $reasons[] = 'container_class_mismatch';
            }

            $className = is_string($metadata['container_class'] ?? null) ? $metadata['container_class'] : null;

            if (is_file($cacheFile) && $className !== null && $className !== '' && ! $this->cacheFileContainsClass($cacheFile, $className)) {
                $reasons[] = 'cache_file_class_mismatch';
            }
        }

        return [
            'usable' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
            'environment' => $this->environment->name,
            'debug' => $this->environment->debug,
            'cache_file' => $cacheFile,
            'cache_file_exists' => is_file($cacheFile),
            'metadata_file' => $metadataFile,
            'metadata_file_exists' => is_file($metadataFile),
            'generated_at' => is_string($metadata['generated_at'] ?? null) ? $metadata['generated_at'] : null,
            'tracked_file_count' => $signature['tracked_file_count'],
            'current_source_hash' => $signature['source_hash'],
            'cached_source_hash' => is_string($metadata['source_hash'] ?? null) ? $metadata['source_hash'] : null,
            'expected_container_class' => $signature['container_class'],
            'cached_container_class' => is_string($metadata['container_class'] ?? null) ? $metadata['container_class'] : null,
        ];
    }

    /**
     * Load the cached container class.
     *
     * @since 0.1.0
     */
    public function load(): ContainerInterface
    {
        $cacheFile = $this->getCacheFile();
        $metadata = $this->readMetadata();

        if (! is_file($cacheFile)) {
            throw new RuntimeException('The Jooosi Mail container cache file does not exist.');
        }

        if (! is_array($metadata)) {
            throw new RuntimeException('The Jooosi Mail container metadata file does not exist or is invalid.');
        }

        $className = is_string($metadata['container_class'] ?? null) ? $metadata['container_class'] : null;

        if ($className === null || $className === '') {
            throw new RuntimeException('The Jooosi Mail container metadata is missing the container class name.');
        }

        if (! $this->cacheFileContainsClass($cacheFile, $className)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('The Jooosi Mail container cache file does not contain the expected container class "%s".', $className));
        }

        if (! class_exists($className, false)) {
            require $cacheFile;
        }

        if (! class_exists($className, false)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('The Jooosi Mail container class "%s" was not found in the cache file.', $className));
        }

        return new $className();
    }

    /**
     * Dump a compiled container class to disk.
     *
     * @since 0.1.0
     */
    public function dump(ContainerBuilder $builder): void
    {
        $this->ensureCacheDirectoryExists();
        $signature = $this->buildSignature();

        $dumper = new PhpDumper($builder);
        $php = $dumper->dump([
            'class' => $signature['container_class'],
        ]);

        $this->writePhpFile($this->getCacheFile(), $php);
        $this->writePhpFile($this->getMetadataFile(), sprintf(
            "<?php\n\ndeclare(strict_types=1);\n\nreturn %s;\n",
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Intentional: generates a PHP cache file consumed via require.
            var_export([
                'generated_at' => gmdate('Y-m-d H:i:s'),
                'environment' => $this->environment->name,
                'debug' => $this->environment->debug,
                'source_hash' => $signature['source_hash'],
                'container_class' => $signature['container_class'],
                'tracked_file_count' => $signature['tracked_file_count'],
            ], true),
        ));
    }

    /**
     * @since 0.1.0
     */
    public function clear(): void
    {
        $this->deleteFile($this->getCacheFile());
        $this->deleteFile($this->getMetadataFile());
    }

    private function getCacheFile(): string
    {
        return $this->paths->cacheDir . '/container.php';
    }

    private function getMetadataFile(): string
    {
        return $this->paths->cacheDir . '/container.meta.php';
    }

    /**
     * @return array{source_hash: string, container_class: string, tracked_file_count: int}
     *
     * @since 0.1.0
     */
    private function buildSignature(): array
    {
        $files = $this->collectTrackedFiles();
        $context = hash_init('sha256');

        foreach ($files as $file) {
            hash_update($context, str_replace($this->paths->rootDir . '/', '', $file));
            hash_update($context, "\0");

            if (! is_readable($file)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new RuntimeException(sprintf('The Jooosi Mail container source file "%s" is not readable.', $file));
            }

            hash_update_file($context, $file);
            hash_update($context, "\0");
        }

        $sourceHash = hash_final($context);

        return [
            'source_hash' => $sourceHash,
            'container_class' => $this->buildContainerClass($sourceHash),
            'tracked_file_count' => count($files),
        ];
    }

    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function collectTrackedFiles(): array
    {
        $files = [];

        foreach (['src', 'composer.json', 'composer.lock', 'constant.php', 'jooosi-mail.php'] as $path) {
            $absolutePath = $this->paths->rootDir . '/' . $path;

            if (is_dir($absolutePath)) {
                $files = [...$files, ...$this->collectPhpFiles($absolutePath)];

                continue;
            }

            if (is_file($absolutePath)) {
                $files[] = $absolutePath;
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function collectPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @since 0.1.0
     */
    private function readMetadata(): ?array
    {
        $metadataFile = $this->getMetadataFile();

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
     * @since 0.1.0
     */
    private function buildContainerClass(string $sourceHash): string
    {
        return 'JooosiMailCachedContainer_' . substr($sourceHash, 0, 12);
    }

    /**
     * @since 0.1.0
     */
    private function cacheFileContainsClass(string $cacheFile, string $className): bool
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
     * @since 0.1.0
     */
    private function ensureCacheDirectoryExists(): void
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
     * @since 0.1.0
     */
    private function writePhpFile(string $path, string $contents): void
    {
        $temporaryFile = tempnam($this->paths->cacheDir, 'jooosi-mail-');

        if (! is_string($temporaryFile) || $temporaryFile === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to allocate a temporary file for "%s".', $path));
        }

        if (file_put_contents($temporaryFile, $contents, LOCK_EX) === false) {
            $this->deleteFile($temporaryFile);

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to write the Jooosi Mail cache file "%s".', $path));
        }

        global $wp_filesystem;
        if ($wp_filesystem === null) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            \WP_Filesystem();
        }

        if (! $wp_filesystem->move($temporaryFile, $path, true)) {
            $this->deleteFile($temporaryFile);

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to move the Jooosi Mail cache file into place at "%s".', $path));
        }

        $this->invalidateOpcodeCache($path);
    }

    /**
     * @since 0.1.0
     */
    private function deleteFile(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $this->invalidateOpcodeCache($path);

        wp_delete_file($path);
    }

    /**
     * @since 0.1.0
     */
    private function invalidateOpcodeCache(string $path): void
    {
        clearstatcache(true, $path);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}

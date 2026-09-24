<?php

declare(strict_types=1);

namespace JooosiMail\Infrastructure\Container;

use JooosiMail\Bootstrap\Environment;
use JooosiMail\Bootstrap\Paths;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

/**
 * Loads and dumps the compiled Symfony container.
 *
 * @since 0.1.0
 */
final class ContainerCache
{
    private readonly ContainerCacheSignature $signature;

    private readonly ContainerCacheArtifactStore $artifactStore;

    private readonly ContainerCacheBuildLock $buildLock;

    private readonly Environment $environment;

    public function __construct(
        Paths $paths,
        Environment $environment,
    ) {
        $this->signature = new ContainerCacheSignature($paths, $environment);
        $this->artifactStore = new ContainerCacheArtifactStore($paths);
        $this->buildLock = new ContainerCacheBuildLock($this->artifactStore);
        $this->environment = $environment;
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
     *     current_context_hash: string,
     *     cached_context_hash: ?string,
     *     expected_container_class: string,
     *     cached_container_class: ?string
     * }
     *
     * @since 0.1.0
     */
    public function inspect(): array
    {
        $signature = $this->signature->build();
        $cacheFile = $this->artifactStore->cacheFile();
        $metadataFile = $this->artifactStore->metadataFile();
        $metadata = $this->artifactStore->readMetadata();
        $reasons = [];

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

            if (($metadata['context_hash'] ?? null) !== $signature['context_hash']) {
                $reasons[] = 'context_hash_mismatch';
            }

            if (($metadata['container_class'] ?? null) !== $signature['container_class']) {
                $reasons[] = 'container_class_mismatch';
            }

            $className = is_string($metadata['container_class'] ?? null) ? $metadata['container_class'] : null;

            if (is_file($cacheFile) && $className !== null && $className !== '' && ! $this->artifactStore->containsClass($cacheFile, $className)) {
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
            'current_context_hash' => $signature['context_hash'],
            'cached_context_hash' => is_string($metadata['context_hash'] ?? null) ? $metadata['context_hash'] : null,
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
        $cacheFile = $this->artifactStore->cacheFile();
        $metadata = $this->artifactStore->readMetadata();

        if (! is_file($cacheFile)) {
            throw new RuntimeException('The Jooosi Mail container cache file does not exist.');
        }

        if (! is_array($metadata)) {
            throw new RuntimeException('The Jooosi Mail container metadata file does not exist or is invalid.');
        }

        if (($metadata['context_hash'] ?? null) !== $this->signature->contextHash()) {
            throw new RuntimeException('The Jooosi Mail container cache was built for different runtime paths or environment.');
        }

        $className = is_string($metadata['container_class'] ?? null) ? $metadata['container_class'] : null;

        if ($className === null || $className === '') {
            throw new RuntimeException('The Jooosi Mail container metadata is missing the container class name.');
        }

        if (! $this->artifactStore->containsClass($cacheFile, $className)) {
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
        $this->withBuildLock(function () use ($builder): void {
            $this->dumpUnlocked($builder);
        });
    }

    /**
     * Execute work while holding the container cache build lock.
     *
     * @template T
     *
     * @param callable(): T $callback
     * @return T
     *
     * @since 0.1.0
     */
    public function withBuildLock(callable $callback): mixed
    {
        return $this->buildLock->run($callback);
    }

    /**
     * @since 0.1.0
     */
    public function clear(): void
    {
        $this->withBuildLock(function (): void {
            $this->artifactStore->deleteFile($this->artifactStore->cacheFile());
            $this->artifactStore->deleteFile($this->artifactStore->metadataFile());
        });
    }

    /**
     * @since 0.1.0
     */
    private function dumpUnlocked(ContainerBuilder $builder): void
    {
        $signature = $this->signature->build();

        $dumper = new PhpDumper($builder);
        $php = $dumper->dump([
            'class' => $signature['container_class'],
        ]);

        $this->artifactStore->writePhpFile($this->artifactStore->cacheFile(), $php);
        $this->artifactStore->writePhpFile($this->artifactStore->metadataFile(), sprintf(
            "<?php\n\ndeclare(strict_types=1);\n\nreturn %s;\n",
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Intentional: generates a PHP cache file consumed via require.
            var_export([
                'generated_at' => gmdate('Y-m-d H:i:s'),
                'environment' => $this->environment->name,
                'debug' => $this->environment->debug,
                'source_hash' => $signature['source_hash'],
                'context_hash' => $signature['context_hash'],
                'container_class' => $signature['container_class'],
                'tracked_file_count' => $signature['tracked_file_count'],
            ], true),
        ));
    }
}

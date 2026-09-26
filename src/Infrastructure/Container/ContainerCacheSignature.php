<?php

declare (strict_types=1);
namespace JooosiMail\Infrastructure\Container;

use JooosiMail\Bootstrap\Environment;
use JooosiMail\Bootstrap\Paths;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
/**
 * Builds source and runtime-context identities for a compiled container.
 *
 * @since 1.0.9
 */
final class ContainerCacheSignature
{
    public function __construct(private readonly Paths $paths, private readonly Environment $environment)
    {
    }
    /**
     * @return array{source_hash: string, context_hash: string, container_class: string, tracked_file_count: int}
     *
     * @since 1.0.9
     */
    public function build(): array
    {
        $files = $this->collectTrackedFiles();
        $context = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($context, str_replace($this->paths->rootDir . '/', '', $file));
            hash_update($context, "\x00");
            if (!is_readable($file)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new RuntimeException(sprintf('The Jooosi Mail container source file "%s" is not readable.', $file));
            }
            hash_update_file($context, $file);
            hash_update($context, "\x00");
        }
        $sourceHash = hash_final($context);
        $contextHash = $this->contextHash();
        return ['source_hash' => $sourceHash, 'context_hash' => $contextHash, 'container_class' => $this->containerClass($sourceHash, $contextHash), 'tracked_file_count' => count($files)];
    }
    /**
     * Identify the runtime context embedded in the compiled service definitions.
     *
     * @since 1.0.9
     */
    public function contextHash(): string
    {
        return hash('sha256', implode("\x00", [$this->paths->pluginFile, $this->paths->rootDir, $this->paths->srcDir, $this->paths->cacheDir, $this->paths->documentationDir, $this->environment->name, $this->environment->debug ? '1' : '0']));
    }
    /**
     * @return list<string>
     *
     * @since 1.0.9
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
     * @since 1.0.9
     */
    private function collectPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $files[] = $file->getPathname();
        }
        return $files;
    }
    /**
     * @since 1.0.9
     */
    private function containerClass(string $sourceHash, string $contextHash): string
    {
        return 'JooosiMailCachedContainer_' . substr(hash('sha256', $sourceHash . "\x00" . $contextHash), 0, 24);
    }
}

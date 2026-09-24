<?php

declare(strict_types=1);

namespace JooosiMail\Tests\Integration\Infrastructure\Container;

use JooosiMail\Bootstrap\Environment;
use JooosiMail\Bootstrap\Paths;
use JooosiMail\Infrastructure\Container\ContainerCache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;
use WP_UnitTestCase;

/**
 * Covers compiled container cache validation.
 *
 * @since 0.1.0
 */
final class ContainerCacheTest extends WP_UnitTestCase
{
    /**
     * @since 0.1.0
     */
    private ?string $rootDir = null;

    /**
     * @since 1.0.8
     */
    private ?string $relocatedRootDir = null;

    /**
     * @since 0.1.0
     */
    public function tear_down(): void
    {
        if ($this->relocatedRootDir !== null) {
            $this->removeDirectory($this->relocatedRootDir);
            $this->relocatedRootDir = null;
        }

        if ($this->rootDir !== null) {
            $this->removeDirectory($this->rootDir);
            $this->rootDir = null;
        }

        parent::tear_down();
    }

    /**
     * @since 0.1.0
     */
    public function testInspectRejectsCacheFileWhenMetadataClassIsMissingFromCacheFile(): void
    {
        $cache = $this->createCache();
        $builder = new ContainerBuilder();
        $builder->register('jooosi_mail.cache_test_service', stdClass::class)->setPublic(true);
        $builder->compile();

        $cache->dump($builder);

        self::assertTrue($cache->inspect()['usable']);

        $staleClassName = 'JooosiMailCachedContainerStale_' . str_replace('.', '_', uniqid('', true));
        $this->writeFile($this->rootDir . '/var/cache/container.php', sprintf("<?php\n\nclass %s\n{\n}\n", $staleClassName));

        $inspection = $cache->inspect();

        self::assertFalse($inspection['usable']);
        self::assertContains('cache_file_class_mismatch', $inspection['reasons']);

        try {
            $cache->load();
            self::fail('Expected the mismatched cache file to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('does not contain the expected container class', $exception->getMessage());
        }

        self::assertFalse(class_exists($staleClassName, false));
    }

    /**
     * @since 0.1.0
     */
    public function testInspectAllowsFreshCacheInDebugMode(): void
    {
        $cache = $this->createCache(debug: true);
        $builder = new ContainerBuilder();
        $builder->register('jooosi_mail.cache_test_service', stdClass::class)->setPublic(true);
        $builder->compile();

        $cache->dump($builder);

        $inspection = $cache->inspect();

        self::assertTrue($inspection['usable']);
        self::assertSame([], $inspection['reasons']);
        self::assertTrue($inspection['debug']);
    }

    /**
     * Copied cache files must not reuse paths or a PHP class from the original installation.
     *
     * @since 1.0.8
     */
    public function testRelocatedCacheIsRebuiltWithDistinctPathsInTheSameProcess(): void
    {
        $originalCache = $this->createCache();
        $originalPaths = Paths::fromPluginFile($this->rootDir . '/jooosi-mail.php');
        $originalCache->dump($this->createPathsContainer($originalPaths));
        $originalContainer = $originalCache->load();

        $this->relocatedRootDir = $this->rootDir . '-relocated';
        $relocatedPaths = Paths::fromPluginFile($this->relocatedRootDir . '/jooosi-mail.php');
        $this->createDirectory($relocatedPaths->srcDir);
        $this->createDirectory($relocatedPaths->cacheDir);

        foreach (['composer.json', 'jooosi-mail.php', 'src/Tracked.php', 'var/cache/container.php', 'var/cache/container.meta.php'] as $file) {
            self::assertTrue(copy($originalPaths->rootDir . '/' . $file, $relocatedPaths->rootDir . '/' . $file));
        }

        $relocatedCache = new ContainerCache($relocatedPaths, new Environment(false, 'production'));
        $inspection = $relocatedCache->inspect();

        self::assertFalse($inspection['usable']);
        self::assertSame($inspection['cached_source_hash'], $inspection['current_source_hash']);
        self::assertContains('context_hash_mismatch', $inspection['reasons']);
        self::assertContains('container_class_mismatch', $inspection['reasons']);

        try {
            $relocatedCache->load();
            self::fail('Expected direct loading of a relocated cache to fail.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('different runtime paths or environment', $exception->getMessage());
        }

        $relocatedCache->dump($this->createPathsContainer($relocatedPaths));
        $relocatedContainer = $relocatedCache->load();

        self::assertTrue($relocatedCache->isUsable());
        self::assertSame($originalPaths->rootDir, $originalContainer->get(Paths::class)->rootDir);
        self::assertSame($relocatedPaths->rootDir, $relocatedContainer->get(Paths::class)->rootDir);
        self::assertNotSame(get_class($originalContainer), get_class($relocatedContainer));
        self::assertSame(get_class($relocatedContainer), get_class($relocatedCache->load()));
    }

    /**
     * @since 1.0.8
     */
    public function testCacheIdentityIncludesPluginEntrypointAndEnvironment(): void
    {
        $cache = $this->createCache();
        $paths = Paths::fromPluginFile($this->rootDir . '/jooosi-mail.php');
        $cache->dump($this->createPathsContainer($paths));
        $inspection = $cache->inspect();

        $variants = [
            new ContainerCache(Paths::fromPluginFile($this->rootDir . '/alternate.php'), new Environment(false, 'production')),
            new ContainerCache($paths, new Environment(false, 'staging')),
            new ContainerCache($paths, new Environment(true, 'production')),
        ];

        foreach ($variants as $variant) {
            $variantInspection = $variant->inspect();

            self::assertFalse($variantInspection['usable']);
            self::assertSame($inspection['current_source_hash'], $variantInspection['current_source_hash']);
            self::assertContains('context_hash_mismatch', $variantInspection['reasons']);
            self::assertNotSame($inspection['expected_container_class'], $variantInspection['expected_container_class']);
        }
    }

    /**
     * Existing installations rebuild metadata created before context validation was added.
     *
     * @since 1.0.8
     */
    public function testCacheWithoutContextMetadataIsInvalidated(): void
    {
        $cache = $this->createCache();
        $paths = Paths::fromPluginFile($this->rootDir . '/jooosi-mail.php');
        $builder = $this->createPathsContainer($paths);
        $cache->dump($builder);
        $metadataPath = $paths->cacheDir . '/container.meta.php';
        $metadata = require $metadataPath;
        unset($metadata['context_hash']);
        $this->writeFile($metadataPath, sprintf("<?php\nreturn %s;\n", var_export($metadata, true)));

        self::assertFalse($cache->isUsable());
        self::assertContains('context_hash_mismatch', $cache->inspect()['reasons']);

        $cache->dump($builder);

        self::assertTrue($cache->isUsable());
        self::assertSame($paths->rootDir, $cache->load()->get(Paths::class)->rootDir);
    }

    /**
     * @since 1.0.9
     */
    public function testInspectRejectsMetadataThatCannotBeLoaded(): void
    {
        $cache = $this->createCache();
        $builder = $this->createPathsContainer(Paths::fromPluginFile($this->rootDir . '/jooosi-mail.php'));
        $cache->dump($builder);
        $metadataPath = $this->rootDir . '/var/cache/container.meta.php';
        $this->writeFile($metadataPath, "<?php throw new RuntimeException('invalid metadata');\n");

        $inspection = $cache->inspect();

        self::assertFalse($inspection['usable']);
        self::assertSame(['invalid_metadata_file'], $inspection['reasons']);

        try {
            $cache->load();
            self::fail('Expected invalid metadata to prevent loading the cache.');
        } catch (RuntimeException $exception) {
            self::assertSame('The Jooosi Mail container metadata file does not exist or is invalid.', $exception->getMessage());
        }
    }

    /**
     * @since 0.1.0
     */
    public function testBuildLockIsReentrantAndReleasedAfterFailure(): void
    {
        $cache = $this->createCache();

        $result = $cache->withBuildLock(static fn (): string => $cache->withBuildLock(static fn (): string => 'locked'));

        self::assertSame('locked', $result);

        try {
            $cache->withBuildLock(static function (): void {
                throw new RuntimeException('Expected lock callback failure.');
            });
            self::fail('Expected the lock callback to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Expected lock callback failure.', $exception->getMessage());
        }

        self::assertSame('released', $cache->withBuildLock(static fn (): string => 'released'));
    }

    /**
     * @since 0.1.0
     */
    public function testBuildLockSerializesConcurrentProcesses(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            self::markTestSkipped('The PCNTL extension is required for the container cache concurrency test.');
        }

        $cache = $this->createCache();
        $eventsFile = $this->rootDir . '/lock-events.log';
        $firstPid = pcntl_fork();

        if ($firstPid === -1) {
            self::fail('Unable to fork the first container cache lock worker.');
        }

        if ($firstPid === 0) {
            $this->runLockWorker($cache, $eventsFile, 'first', 500_000);
        }

        $this->waitForLockEvent($eventsFile, 'first:start');
        $secondPid = pcntl_fork();

        if ($secondPid === -1) {
            pcntl_waitpid($firstPid, $firstStatus);
            self::fail('Unable to fork the second container cache lock worker.');
        }

        if ($secondPid === 0) {
            $this->runLockWorker($cache, $eventsFile, 'second', 0);
        }

        pcntl_waitpid($firstPid, $firstStatus);
        pcntl_waitpid($secondPid, $secondStatus);

        self::assertTrue(pcntl_wifexited($firstStatus));
        self::assertSame(0, pcntl_wexitstatus($firstStatus));
        self::assertTrue(pcntl_wifexited($secondStatus));
        self::assertSame(0, pcntl_wexitstatus($secondStatus));

        $events = file($eventsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        self::assertSame([
            'first:start',
            'first:end',
            'second:start',
            'second:end',
        ], $events);
    }

    /**
     * @since 0.1.0
     */
    private function createCache(bool $debug = false): ContainerCache
    {
        $this->rootDir = sys_get_temp_dir() . '/jooosi-mail-container-cache-' . str_replace('.', '', uniqid('', true));
        $fixtureId = str_replace('.', '', uniqid('', true));

        $this->createDirectory($this->rootDir . '/src');
        $this->createDirectory($this->rootDir . '/var/cache');
        $this->writeFile($this->rootDir . '/composer.json', "{}\n");
        $this->writeFile($this->rootDir . '/jooosi-mail.php', "<?php\n");
        $this->writeFile($this->rootDir . '/src/Tracked.php', sprintf("<?php\n\ndeclare(strict_types=1);\n\nnamespace JooosiMail\\Tests\\Fixture;\n\nfinal class ContainerCacheTracked%s\n{\n}\n", $fixtureId));

        return new ContainerCache(
            new Paths(
                pluginFile: $this->rootDir . '/jooosi-mail.php',
                rootDir: $this->rootDir,
                srcDir: $this->rootDir . '/src',
                cacheDir: $this->rootDir . '/var/cache',
                documentationDir: $this->rootDir . '/documentation',
            ),
            new Environment(debug: $debug, name: $debug ? 'development' : 'production'),
        );
    }

    /**
     * Register the same path factory used by the runtime container.
     *
     * @since 1.0.8
     */
    private function createPathsContainer(Paths $paths): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->register(Paths::class, Paths::class)
            ->setPublic(true)
            ->setFactory([Paths::class, 'fromPluginFile'])
            ->addArgument($paths->pluginFile);
        $builder->compile();

        return $builder;
    }

    /**
     * @since 0.1.0
     */
    private function runLockWorker(ContainerCache $cache, string $eventsFile, string $name, int $holdMicroseconds): void
    {
        try {
            $cache->withBuildLock(static function () use ($eventsFile, $name, $holdMicroseconds): void {
                file_put_contents($eventsFile, $name . ":start\n", FILE_APPEND | LOCK_EX);

                if ($holdMicroseconds > 0) {
                    usleep($holdMicroseconds);
                }

                file_put_contents($eventsFile, $name . ":end\n", FILE_APPEND | LOCK_EX);
            });
        } catch (Throwable) {
            exit(1);
        }

        exit(0);
    }

    /**
     * @since 0.1.0
     */
    private function waitForLockEvent(string $eventsFile, string $event): void
    {
        $deadline = microtime(true) + 2.0;

        do {
            if (is_readable($eventsFile)) {
                $contents = file_get_contents($eventsFile);

                if (is_string($contents) && str_contains($contents, $event)) {
                    return;
                }
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail(sprintf('Timed out waiting for the container cache lock event "%s".', $event));
    }

    /**
     * @since 0.1.0
     */
    private function createDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            self::fail(sprintf('Unable to create test directory "%s".', $directory));
        }
    }

    /**
     * @since 0.1.0
     */
    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            self::fail(sprintf('Unable to write test file "%s".', $path));
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    /**
     * @since 0.1.0
     */
    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($directory);
    }
}

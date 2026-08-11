<?php

declare (strict_types=1);
namespace JooosiMail\Bootstrap;

/**
 * Runtime filesystem paths for Jooosi Mail.
 *
 * @since 0.1.0
 */
final class Paths
{
    public function __construct(public readonly string $pluginFile, public readonly string $rootDir, public readonly string $srcDir, public readonly string $cacheDir, public readonly string $documentationDir)
    {
    }
    /**
     * Build runtime paths from the main plugin file.
     *
     * @since 0.1.0
     */
    public static function fromPluginFile(string $pluginFile): self
    {
        $rootDir = dirname($pluginFile);
        return new self(pluginFile: $pluginFile, rootDir: $rootDir, srcDir: $rootDir . '/src', cacheDir: $rootDir . '/var/cache', documentationDir: $rootDir . '/documentation');
    }
}

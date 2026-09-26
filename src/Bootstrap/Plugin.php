<?php

declare (strict_types=1);
namespace JooosiMail\Bootstrap;

/**
 * Main plugin entrypoint.
 *
 * @since 0.1.0
 */
final class Plugin
{
    private static ?self $instance = null;
    private ?\JooosiMail\Bootstrap\Kernel $kernel = null;
    private function __construct(private readonly \JooosiMail\Bootstrap\Paths $paths, private readonly \JooosiMail\Bootstrap\Environment $environment)
    {
    }
    /**
     * Boot the plugin singleton.
     *
     * @since 0.1.0
     */
    public static function boot(string $pluginFile): self
    {
        if (self::$instance instanceof self) {
            self::$instance->bootKernel();
            return self::$instance;
        }
        $paths = \JooosiMail\Bootstrap\Paths::fromPluginFile($pluginFile);
        $environment = \JooosiMail\Bootstrap\Environment::fromWordPress();
        self::$instance = new self($paths, $environment);
        self::$instance->registerLifecycleHooks();
        self::$instance->bootKernel();
        return self::$instance;
    }
    /**
     * Register WordPress activation and deactivation hooks.
     *
     * @since 0.1.0
     */
    public function registerLifecycleHooks(): void
    {
        register_activation_hook($this->paths->pluginFile, [$this, 'activate']);
        register_deactivation_hook($this->paths->pluginFile, [$this, 'deactivate']);
    }
    /**
     * WordPress activation callback.
     *
     * @since 0.1.0
     */
    public function activate(): void
    {
        $this->getKernel()->activate();
    }
    /**
     * WordPress deactivation callback.
     *
     * @since 0.1.0
     */
    public function deactivate(): void
    {
        $this->getKernel()->deactivate();
    }
    private function bootKernel(): void
    {
        $this->getKernel()->boot();
    }
    private function getKernel(): \JooosiMail\Bootstrap\Kernel
    {
        if (!$this->kernel instanceof \JooosiMail\Bootstrap\Kernel) {
            $this->kernel = new \JooosiMail\Bootstrap\Kernel($this->paths, $this->environment);
        }
        return $this->kernel;
    }
}

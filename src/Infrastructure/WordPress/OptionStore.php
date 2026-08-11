<?php

declare (strict_types=1);
namespace JooosiMail\Infrastructure\WordPress;

use JooosiMail\Settings\Config;
/**
 * Small wrapper around centralized Jooosi Mail config paths.
 *
 * @since 0.1.0
 */
final class OptionStore
{
    public function __construct(private readonly Config $config)
    {
    }
    /**
     * Read a nested config or state value.
     *
     * @since 0.1.0
     */
    public function get(string $path, mixed $default = null): mixed
    {
        return $this->config->get($path, $default);
    }
    /**
     * Persist a nested config or state value.
     *
     * @since 0.1.0
     */
    public function set(string $path, mixed $value): bool
    {
        return $this->config->set($path, $value);
    }
    /**
     * Delete a nested config or state value.
     *
     * @since 0.1.0
     */
    public function delete(string $path): bool
    {
        return $this->config->delete($path);
    }
}

<?php

declare (strict_types=1);
namespace JooosiMail\Settings;

use JooosiMail\Discovery\Attribute\Service;
/**
 * Stores plugin settings and cold state in a single option payload.
 *
 * Dot notation paths are resolved inside the shared `jooosi_mail_config` option.
 *
 * @since 0.1.0
 */
#[Service]
final class Config
{
    /**
     * @var string
     */
    private const OPTION_KEY = 'jooosi_mail_config';
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function all(): array
    {
        return $this->load();
    }
    /**
     * Gets a value from the shared config payload.
     *
     * @since 0.1.0
     */
    public function get(string $path, mixed $defaultValue = null): mixed
    {
        $segments = $this->normalizePath($path);
        if ($segments === []) {
            return $this->load();
        }
        $value = $this->load();
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $defaultValue;
            }
            $value = $value[$segment];
        }
        return $value;
    }
    /**
     * Sets a value inside the shared config payload.
     *
     * @since 0.1.0
     */
    public function set(string $path, mixed $value): bool
    {
        $segments = $this->normalizePath($path);
        if ($segments === []) {
            return $this->persist(is_array($value) ? $value : []);
        }
        $options = $this->load();
        $cursor =& $options;
        $lastIndex = count($segments) - 1;
        foreach ($segments as $index => $segment) {
            if ($index === $lastIndex) {
                $cursor[$segment] = $value;
                return $this->persist($options);
            }
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor =& $cursor[$segment];
        }
        return $this->persist($options);
    }
    /**
     * Deletes a value from the shared config payload.
     *
     * @since 0.1.0
     */
    public function delete(string $path): bool
    {
        $segments = $this->normalizePath($path);
        if ($segments === []) {
            return delete_option(self::OPTION_KEY);
        }
        $options = $this->load();
        if (!$this->deletePath($options, $segments)) {
            return \false;
        }
        return $this->persist($options);
    }
    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function load(): array
    {
        $options = get_option(self::OPTION_KEY, []);
        return is_array($options) ? $options : [];
    }
    /**
     * @param array<string, mixed> $options
     *
     * @since 0.1.0
     */
    private function persist(array $options): bool
    {
        if ($options === []) {
            return delete_option(self::OPTION_KEY);
        }
        return update_option(self::OPTION_KEY, $options, \false);
    }
    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function normalizePath(string $path): array
    {
        $segments = array_filter(explode('.', trim($path, '.')), static fn(string $segment): bool => $segment !== '');
        return array_values($segments);
    }
    /**
     * @param array<string, mixed> $options
     * @param list<string>         $segments
     *
     * @since 0.1.0
     */
    private function deletePath(array &$options, array $segments): bool
    {
        $segment = array_shift($segments);
        if ($segment === null || !array_key_exists($segment, $options)) {
            return \false;
        }
        if ($segments === []) {
            unset($options[$segment]);
            return \true;
        }
        if (!is_array($options[$segment])) {
            return \false;
        }
        $deleted = $this->deletePath($options[$segment], $segments);
        if ($deleted && $options[$segment] === []) {
            unset($options[$segment]);
        }
        return $deleted;
    }
}

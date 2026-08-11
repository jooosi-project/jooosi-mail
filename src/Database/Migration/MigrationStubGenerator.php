<?php

declare (strict_types=1);
namespace JooosiMail\Database\Migration;

use InvalidArgumentException;
use JooosiMail\Discovery\Attribute\Service;
use RuntimeException;
/**
 * Generates versioned schema migration stubs.
 *
 * @since 0.1.0
 */
#[Service]
final class MigrationStubGenerator
{
    public function __construct(private readonly \JooosiMail\Database\Migration\MigrationRegistry $migrationRegistry)
    {
    }
    /**
     * @return array{path: string, className: class-string<MigrationInterface>, version: string, description: string}
     *
     * @since 0.1.0
     */
    public function generate(string $name): array
    {
        $suffix = $this->normalizeSuffix($name);
        if ($suffix === '') {
            throw new InvalidArgumentException('Provide a migration name containing letters or numbers.');
        }
        $description = $this->buildDescription($suffix);
        $version = $this->nextVersion();
        $className = sprintf('Version%s%s', $version, $suffix);
        $directory = $this->migrationRegistry->directory();
        $path = $directory . '/' . $className . '.php';
        $this->ensureDirectoryExists($directory);
        if (file_exists($path)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('The Jooosi Mail migration file "%s" already exists.', $path));
        }
        $stub = $this->buildStub($className, $version, $description);
        if (file_put_contents($path, $stub, \LOCK_EX) === \false) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('Unable to write the Jooosi Mail migration file "%s".', $path));
        }
        return ['path' => $path, 'className' => sprintf('%s\%s', self::migrationsNamespace(), $className), 'version' => $version, 'description' => $description];
    }
    /**
     * @since 0.1.0
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }
        $created = wp_mkdir_p($directory);
        if (!$created && !is_dir($directory)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException(sprintf('The Jooosi Mail migrations directory "%s" could not be created.', $directory));
        }
    }
    /**
     * @since 0.1.0
     */
    private function nextVersion(): string
    {
        $datePrefix = function_exists('wp_date') ? wp_date('Ymd') : gmdate('Ymd');
        $sequence = 1;
        $files = glob($this->migrationRegistry->directory() . '/Version' . $datePrefix . '*.php');
        if (is_array($files)) {
            foreach ($files as $file) {
                $filename = pathinfo($file, \PATHINFO_BASENAME);
                if (preg_match('/^Version' . preg_quote($datePrefix, '/') . '(\d{4}).*\.php$/', $filename, $matches) !== 1) {
                    continue;
                }
                $sequence = max($sequence, (int) $matches[1] + 1);
            }
        }
        return sprintf('%s%04d', $datePrefix, $sequence);
    }
    /**
     * @since 0.1.0
     */
    private function normalizeSuffix(string $name): string
    {
        $separatedName = preg_replace('/(?<=[a-z0-9])([A-Z])/', ' $1', trim($name)) ?? trim($name);
        $normalizedName = preg_replace('/[^A-Za-z0-9]+/', ' ', $separatedName) ?? '';
        $parts = preg_split('/\s+/', trim($normalizedName), -1, \PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || $parts === []) {
            return '';
        }
        return implode('', array_map(static fn(string $part): string => ucfirst(strtolower($part)), $parts));
    }
    /**
     * @since 0.1.0
     */
    private function buildDescription(string $suffix): string
    {
        $words = preg_split('/(?=[A-Z])/', $suffix, -1, \PREG_SPLIT_NO_EMPTY);
        if (!is_array($words) || $words === []) {
            return 'Applies a Jooosi Mail schema migration.';
        }
        $firstWord = strtolower((string) array_shift($words));
        $verbs = ['add' => 'Adds', 'alter' => 'Alters', 'backfill' => 'Backfills', 'create' => 'Creates', 'delete' => 'Deletes', 'drop' => 'Drops', 'populate' => 'Populates', 'remove' => 'Removes', 'rename' => 'Renames', 'update' => 'Updates'];
        if (isset($verbs[$firstWord])) {
            $subject = $words === [] ? 'a Jooosi Mail schema change' : strtolower(implode(' ', $words));
            return sprintf('%s %s.', $verbs[$firstWord], $subject);
        }
        $description = strtolower(implode(' ', array_merge([$firstWord], $words)));
        return sprintf('Applies the %s migration.', $description);
    }
    /**
     * @since 0.1.0
     */
    private function buildStub(string $className, string $version, string $description): string
    {
        return sprintf(<<<'PHP'
<?php

declare(strict_types=1);

namespace %s;

use Doctrine\DBAL\Connection;
use JooosiMail\Database\Migration\MigrationInterface;
use JooosiMail\Infrastructure\Database\TableNameResolver;

/**
 * %s
 *
 * @since 0.1.0
 */
final class %s implements MigrationInterface
{
    public function getVersion(): string
    {
        return '%s';
    }

    public function getDescription(): string
    {
        return '%s';
    }

    public function up(Connection $connection, TableNameResolver $tableNameResolver): void
    {
    }

    public function down(Connection $connection, TableNameResolver $tableNameResolver): void
    {
    }
}
PHP
, self::migrationsNamespace(), $description, $className, $version, $description);
    }
    /**
     * @since 0.1.0
     */
    private static function migrationsNamespace(): string
    {
        return __NAMESPACE__ . '\Versions';
    }
}

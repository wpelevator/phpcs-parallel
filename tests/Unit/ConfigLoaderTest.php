<?php

declare(strict_types=1);

namespace WPElevator\Pharallel\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPElevator\Pharallel\ConfigLoader;
use WPElevator\Pharallel\Task;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsFiltersVariablesAndDefaults(): void
    {
        $dir = sys_get_temp_dir() . '/pharallel-config-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/pharallel.php', <<<'PHP'
<?php

return [
    'filters' => [
        'package' => static fn (string $path): string => basename(dirname($path)),
    ],
    'variables' => [
        'php' => PHP_BINARY,
    ],
    'defaults' => [
        'path-pattern' => 'packages/*/composer.json',
        'command' => '{php} tool.php {path | package}',
        'processes' => 4,
    ],
];
PHP);

        try {
            $config = (new ConfigLoader())->load('pharallel.php', $dir);

            self::assertArrayHasKey('package', $config->filters);
            self::assertSame('foo', $config->filters['package']('packages/foo/composer.json', new Task('x', 0), $dir));
            self::assertSame(PHP_BINARY, $config->variables['php']);
            self::assertSame(4, $config->defaults['processes']);
        } finally {
            self::rmrf($dir);
        }
    }

    private static function rmrf(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}

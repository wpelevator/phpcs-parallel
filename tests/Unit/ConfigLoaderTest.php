<?php

declare(strict_types=1);

namespace WPElevator\RunParallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\RunParallel\ConfigLoader;
use WPElevator\RunParallel\Task;

final class ConfigLoaderTest extends TestCase
{
    private TempWorkspace $workspace;

    protected function setUp(): void
    {
        $this->workspace = new TempWorkspace('run-parallel-config');
    }

    protected function tearDown(): void
    {
        $this->workspace->cleanup();
    }

    public function testLoadsFiltersVariablesAndDefaults(): void
    {
        $this->workspace->writeFile('run-parallel.php', <<<'PHP'
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

        $config = (new ConfigLoader())->load('run-parallel.php', $this->workspace->dir);

        $this->assertArrayHasKey('package', $config->filters);
        $this->assertSame(
            'foo',
            $config->filters['package']('packages/foo/composer.json', new Task('x', 0), $this->workspace->dir)
        );
        $this->assertSame(PHP_BINARY, $config->variables['php']);
        $this->assertSame(4, $config->defaults['processes']);
    }
}

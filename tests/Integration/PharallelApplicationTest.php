<?php

declare(strict_types=1);

namespace WPElevator\Pharallel\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class PharallelApplicationTest extends TestCase
{
    private string $root;
    private string $php;
    private string $bin;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->php = PHP_BINARY;
        $this->bin = $this->root . '/bin/pharallel';
    }

    public function testRunsCommandPerMatchedPathWithCwdLabelAndCommandOptions(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/packages/a', 0777, true);
            mkdir($dir . '/packages/b', 0777, true);
            file_put_contents($dir . '/packages/a/phpunit.xml.dist', '<phpunit/>');
            file_put_contents($dir . '/packages/b/phpunit.xml.dist', '<phpunit/>');
            self::writeFakeTool($dir . '/fake-tool');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--path-pattern=packages/*/phpunit.xml.dist',
                '--command=' . $dir . '/fake-tool {path} --filter "My Test"',
                '--cwd={path | dirname}',
                '--label={path | dirname | basename}',
                '--processes=2',
            ], $dir);

            self::assertSame(0, $result['code'], $result['stderr']);
            self::assertStringContainsString('[a] TOOL packages/a/phpunit.xml.dist', $result['stdout']);
            self::assertStringContainsString('[b] TOOL packages/b/phpunit.xml.dist', $result['stdout']);
            self::assertStringContainsString('ARGS --filter|My Test', $result['stdout']);
            self::assertStringContainsString('CWD a', $result['stdout']);
            self::assertStringContainsString('CWD b', $result['stdout']);
            self::assertStringContainsString('Summary:', $result['stderr']);
            self::assertStringContainsString('2 passed', $result['stderr']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testDryRunPrintsRenderedCommandsWithoutExecuting(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/packages/a', 0777, true);
            file_put_contents($dir . '/packages/a/phpunit.xml.dist', '<phpunit/>');
            self::writeFakeTool($dir . '/fake-tool');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--path-pattern=packages/*/phpunit.xml.dist',
                '--command=' . $dir . '/fake-tool {path}',
                '--dry-run',
            ], $dir);

            self::assertSame(0, $result['code'], $result['stderr']);
            self::assertStringContainsString('[a] $ ', $result['stdout']);
            self::assertStringContainsString('packages/a/phpunit.xml.dist', $result['stdout']);
            self::assertStringNotContainsString('TOOL', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testFailFastStopsSchedulingAfterFirstFailure(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/packages/a', 0777, true);
            mkdir($dir . '/packages/b', 0777, true);
            file_put_contents($dir . '/packages/a/composer.json', '{}');
            file_put_contents($dir . '/packages/b/composer.json', '{}');
            file_put_contents($dir . '/failing-tool', <<<'PHP'
#!/usr/bin/env php
<?php
echo 'RAN ' . ($argv[1] ?? '') . "\n";
exit(2);
PHP);
            chmod($dir . '/failing-tool', 0755);

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--path-pattern=packages/*/composer.json',
                '--command=' . $dir . '/failing-tool {path}',
                '--fail-fast',
                '--processes=1',
            ], $dir);

            self::assertSame(2, $result['code'], $result['stderr']);
            self::assertStringContainsString('RAN packages/a/composer.json', $result['stdout']);
            self::assertStringNotContainsString('packages/b', $result['stdout']);
            self::assertStringContainsString('1 failed', $result['stderr']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testPathIsRenderedRelativeToInvocationCwdEvenWhenCwdIsSet(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/packages/a', 0777, true);
            file_put_contents($dir . '/packages/a/config.neon', '');
            self::writeFakeTool($dir . '/fake-tool');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--path-pattern=packages/*/config.neon',
                '--command=' . $dir . '/fake-tool {path}',
                '--cwd={path | dirname}',
            ], $dir);

            self::assertSame(0, $result['code'], $result['stderr']);
            self::assertStringContainsString('TOOL packages/a/config.neon', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testConfigProvidesFiltersVariablesAndDefaults(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/packages/a', 0777, true);
            file_put_contents($dir . '/packages/a/composer.json', '{}');
            self::writeFakeTool($dir . '/fake-tool');
            file_put_contents($dir . '/pharallel.php', <<<PHP
<?php

return [
    'filters' => [
        'package' => static fn (string \$path): string => basename(dirname(\$path)),
    ],
    'variables' => [
        'tool' => '{$dir}/fake-tool',
    ],
    'defaults' => [
        'path-pattern' => 'packages/*/composer.json',
        'command' => '{tool} {path | package}',
        'label' => '{path | package}',
        'processes' => 2,
    ],
];
PHP);

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--config=pharallel.php',
            ], $dir);

            self::assertSame(0, $result['code'], $result['stderr']);
            self::assertStringContainsString('[a] TOOL a', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    /** @param list<string> $command */
    private static function runCommand(array $command, string $cwd): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start command');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private static function makeTmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/pharallel-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        return $dir;
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

    private static function writeFakeTool(string $path): void
    {
        file_put_contents($path, <<<'PHP'
#!/usr/bin/env php
<?php
$cwd = basename(getcwd() ?: '');
echo 'TOOL ' . ($argv[1] ?? '') . "\n";
echo 'CWD ' . $cwd . "\n";
echo 'ARGS ' . implode('|', array_slice($argv, 2)) . "\n";
PHP);
        chmod($path, 0755);
    }
}

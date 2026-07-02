<?php

declare(strict_types=1);

namespace WPElevator\Pharallel\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PharallelApplicationTest extends TestCase
{
    private string $php;
    private string $bin;
    private TempWorkspace $workspace;

    protected function setUp(): void
    {
        $this->php = PHP_BINARY;
        $this->bin = dirname(__DIR__, 2) . '/bin/pharallel';
        $this->workspace = new TempWorkspace('pharallel-test');
    }

    protected function tearDown(): void
    {
        $this->workspace->cleanup();
    }

    public function testRunsCommandPerMatchedPathWithCwdLabelAndCommandOptions(): void
    {
        $this->workspace->writeFile('packages/a/phpunit.xml.dist', '<phpunit/>');
        $this->workspace->writeFile('packages/b/phpunit.xml.dist', '<phpunit/>');
        $tool = $this->writeFakeTool();

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--path-pattern=packages/*/phpunit.xml.dist',
            '--command=' . $tool . ' {path} --filter "My Test"',
            '--cwd={path | dirname}',
            '--label={path | dirname | basename}',
            '--processes=2',
        ]);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertStringContainsString('[a] TOOL packages/a/phpunit.xml.dist', $result['stdout']);
        self::assertStringContainsString('[b] TOOL packages/b/phpunit.xml.dist', $result['stdout']);
        self::assertStringContainsString('ARGS --filter|My Test', $result['stdout']);
        self::assertStringContainsString('CWD a', $result['stdout']);
        self::assertStringContainsString('CWD b', $result['stdout']);
        self::assertStringContainsString('Summary:', $result['stderr']);
        self::assertStringContainsString('2 passed', $result['stderr']);
    }

    public function testDryRunPrintsRenderedCommandsWithoutExecuting(): void
    {
        $this->workspace->writeFile('packages/a/phpunit.xml.dist', '<phpunit/>');
        $tool = $this->writeFakeTool();

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--path-pattern=packages/*/phpunit.xml.dist',
            '--command=' . $tool . ' {path}',
            '--dry-run',
        ]);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertStringContainsString('[a] $ ', $result['stdout']);
        self::assertStringContainsString('packages/a/phpunit.xml.dist', $result['stdout']);
        self::assertStringNotContainsString('TOOL', $result['stdout']);
    }

    public function testFailFastStopsSchedulingAfterFirstFailure(): void
    {
        $this->workspace->writeFile('packages/a/composer.json', '{}');
        $this->workspace->writeFile('packages/b/composer.json', '{}');
        $tool = $this->workspace->writeExecutable('failing-tool', <<<'PHP'
#!/usr/bin/env php
<?php
echo 'RAN ' . ($argv[1] ?? '') . "\n";
exit(2);
PHP);

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--path-pattern=packages/*/composer.json',
            '--command=' . $tool . ' {path}',
            '--fail-fast',
            '--processes=1',
        ]);

        self::assertSame(2, $result['code'], $result['stderr']);
        self::assertStringContainsString('RAN packages/a/composer.json', $result['stdout']);
        self::assertStringNotContainsString('packages/b', $result['stdout']);
        self::assertStringContainsString('1 failed', $result['stderr']);
    }

    public function testPathIsRenderedRelativeToInvocationCwdEvenWhenCwdIsSet(): void
    {
        $this->workspace->writeFile('packages/a/config.neon', '');
        $tool = $this->writeFakeTool();

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--path-pattern=packages/*/config.neon',
            '--command=' . $tool . ' {path}',
            '--cwd={path | dirname}',
        ]);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertStringContainsString('TOOL packages/a/config.neon', $result['stdout']);
    }

    public function testConfigProvidesFiltersVariablesAndDefaults(): void
    {
        $this->workspace->writeFile('packages/a/composer.json', '{}');
        $tool = $this->writeFakeTool();
        $this->workspace->writeFile('pharallel.php', <<<PHP
<?php

return [
    'filters' => [
        'package' => static fn (string \$path): string => basename(dirname(\$path)),
    ],
    'variables' => [
        'tool' => '{$tool}',
    ],
    'defaults' => [
        'path-pattern' => 'packages/*/composer.json',
        'command' => '{tool} {path | package}',
        'label' => '{path | package}',
        'processes' => 2,
    ],
];
PHP);

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--config=pharallel.php',
        ]);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertStringContainsString('[a] TOOL a', $result['stdout']);
    }

    /** @param list<string> $command */
    private function runCommand(array $command): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $this->workspace->dir);
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

    private function writeFakeTool(): string
    {
        return $this->workspace->writeExecutable('fake-tool', <<<'PHP'
#!/usr/bin/env php
<?php
$cwd = basename(getcwd() ?: '');
echo 'TOOL ' . ($argv[1] ?? '') . "\n";
echo 'CWD ' . $cwd . "\n";
echo 'ARGS ' . implode('|', array_slice($argv, 2)) . "\n";
PHP);
    }
}

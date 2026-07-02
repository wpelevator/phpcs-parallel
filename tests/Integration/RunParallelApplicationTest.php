<?php

declare(strict_types=1);

namespace WPElevator\RunParallel\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunParallelApplicationTest extends TestCase
{
    private string $php;
    private string $bin;
    private TempWorkspace $workspace;

    protected function setUp(): void
    {
        $this->php = PHP_BINARY;
        $this->bin = dirname(__DIR__, 2) . '/bin/run-parallel';
        $this->workspace = new TempWorkspace('run-parallel-test');
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

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('[a] TOOL packages/a/phpunit.xml.dist', $result['stdout']);
        $this->assertStringContainsString('[b] TOOL packages/b/phpunit.xml.dist', $result['stdout']);
        $this->assertStringContainsString('ARGS --filter|My Test', $result['stdout']);
        $this->assertStringContainsString('CWD a', $result['stdout']);
        $this->assertStringContainsString('CWD b', $result['stdout']);
        $this->assertStringContainsString('Summary:', $result['stderr']);
        $this->assertStringContainsString('2 passed', $result['stderr']);
    }

    public function testRunsCommandOptionsAsIndividualTasks(): void
    {
        $tool = $this->writeFakeTool();

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--processes=2',
            '--command=' . $tool . ' one --flag "with space"',
            '--command=' . $tool . ' two',
        ]);

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('TOOL one', $result['stdout']);
        $this->assertStringContainsString('ARGS --flag|with space', $result['stdout']);
        $this->assertStringContainsString('TOOL two', $result['stdout']);
        $this->assertStringContainsString('2 passed', $result['stderr']);
    }

    public function testRunsPositionalCommandsAsIndividualTasks(): void
    {
        $tool = $this->writeFakeTool();

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--processes=2',
            $tool . ' one',
            $tool . ' two',
        ]);

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('TOOL one', $result['stdout']);
        $this->assertStringContainsString('TOOL two', $result['stdout']);
        $this->assertStringContainsString('2 passed', $result['stderr']);
    }

    public function testCommandListUsesSlugLabelAndSupportsDryRun(): void
    {
        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--dry-run',
            '--command=composer lint',
            '--command=composer test -- --testsuite=Unit',
        ]);

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('[composer-lint] $ composer lint', $result['stdout']);
        $this->assertStringContainsString('composer test -- --testsuite=Unit', $result['stdout']);
    }

    public function testHelpUsesRunParallelCommandName(): void
    {
        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--help',
        ]);

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('run-parallel - run a list of commands', $result['stdout']);
        $this->assertStringContainsString('run-parallel --command=CMD', $result['stdout']);
    }

    public function testPathPatternRunsEachCommandForEachMatchWithDistinctDefaultLabels(): void
    {
        $this->workspace->writeFile('packages/a/composer.json', '{}');
        $this->workspace->writeFile('packages/b/composer.json', '{}');

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--dry-run',
            '--path-pattern=packages/*/composer.json',
            '--command=composer validate {path}',
            '--command=composer test {path | dirname}',
        ]);

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString(
            '[a:composer-validate-path] $ composer validate packages/a/composer.json',
            $result['stdout']
        );
        $this->assertStringContainsString(
            '[a:composer-test-path-dirname] $ composer test packages/a',
            $result['stdout']
        );
        $this->assertStringContainsString(
            '[b:composer-validate-path] $ composer validate packages/b/composer.json',
            $result['stdout']
        );
        $this->assertStringContainsString(
            '[b:composer-test-path-dirname] $ composer test packages/b',
            $result['stdout']
        );
    }

    public function testPathPatternSupportsPositionalCommandTemplate(): void
    {
        $this->workspace->writeFile('packages/a/composer.json', '{}');
        $tool = $this->writeFakeTool();

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--path-pattern=packages/*/composer.json',
            $tool . ' {path}',
        ]);

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('TOOL packages/a/composer.json', $result['stdout']);
    }

    public function testFailsWithoutAnyCommand(): void
    {
        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--path-pattern=packages/*/composer.json',
        ]);

        $this->assertSame(3, $result['code']);
        $this->assertStringContainsString('At least one command is required.', $result['stderr']);
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

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('[a] $ ', $result['stdout']);
        $this->assertStringContainsString('packages/a/phpunit.xml.dist', $result['stdout']);
        $this->assertStringNotContainsString('TOOL', $result['stdout']);
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

        $this->assertSame(2, $result['code'], $result['stderr']);
        $this->assertStringContainsString('RAN packages/a/composer.json', $result['stdout']);
        $this->assertStringNotContainsString('packages/b', $result['stdout']);
        $this->assertStringContainsString('1 failed', $result['stderr']);
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

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('TOOL packages/a/config.neon', $result['stdout']);
    }

    public function testConfigProvidesFiltersVariablesAndDefaults(): void
    {
        $this->workspace->writeFile('packages/a/composer.json', '{}');
        $tool = $this->writeFakeTool();
        $this->workspace->writeFile('run-parallel.php', <<<PHP
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
            '--config=run-parallel.php',
        ]);

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('[a] TOOL a', $result['stdout']);
    }

    public function testConfigCommandListRunsWithoutPathPatterns(): void
    {
        $tool = $this->writeFakeTool();
        $this->workspace->writeFile('run-parallel.php', <<<PHP
<?php

return [
    'defaults' => [
        'command' => ['{$tool} one', '{$tool} two'],
    ],
];
PHP);

        $result = $this->runCommand([
            $this->php,
            $this->bin,
            '--config=run-parallel.php',
        ]);

        $this->assertSame(0, $result['code'], $result['stderr']);
        $this->assertStringContainsString('TOOL one', $result['stdout']);
        $this->assertStringContainsString('TOOL two', $result['stdout']);
        $this->assertStringContainsString('2 passed', $result['stderr']);
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

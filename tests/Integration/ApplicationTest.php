<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    private string $root;
    private string $php;
    private string $bin;
    private string $phpcbfBin;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->php = PHP_BINARY;
        $this->bin = $this->root . '/bin/phpcs-parallel';
        $this->phpcbfBin = $this->root . '/bin/phpcbf-parallel';
    }

    public function testDiscoversConfigsAndFiltersWithRepeatableConfigPatterns(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/wp-content/plugins/plugin-a', 0777, true);
            mkdir($dir . '/wp-content/themes/theme-a', 0777, true);
            mkdir($dir . '/wp-content/mu-plugins/mu-a', 0777, true);
            file_put_contents($dir . '/wp-content/plugins/plugin-a/phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/wp-content/themes/theme-a/phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/wp-content/mu-plugins/mu-a/phpcs.xml.dist', '<ruleset/>');
            self::writeFakePhpcs($dir . '/fake-phpcs');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--bin=' . $dir . '/fake-phpcs',
                '--config-pattern=wp-content/plugins/*/phpcs.xml.dist',
                '--config-pattern=wp-content/themes/*/phpcs.xml.dist',
            ], $dir);

            self::assertSame(0, $result['code']);
            self::assertStringContainsString('FAKE plugin-a/phpcs.xml.dist', $result['stdout']);
            self::assertStringContainsString('FAKE theme-a/phpcs.xml.dist', $result['stdout']);
            self::assertStringNotContainsString('mu-a/phpcs.xml.dist', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testSkipsVendorDirectoriesDuringDiscovery(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/app', 0777, true);
            mkdir($dir . '/vendor/package', 0777, true);
            file_put_contents($dir . '/app/phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/vendor/package/phpcs.xml.dist', '<ruleset/>');
            self::writeFakePhpcs($dir . '/fake-phpcs');

            $result = self::runCommand([$this->php, $this->bin, '--bin=' . $dir . '/fake-phpcs'], $dir);

            self::assertSame(0, $result['code']);
            self::assertStringContainsString('FAKE app/phpcs.xml.dist', $result['stdout']);
            self::assertStringNotContainsString('package/phpcs.xml.dist', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testAggregatesChildExitCodes(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/ok', 0777, true);
            mkdir($dir . '/exit-two', 0777, true);
            file_put_contents($dir . '/ok/phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/exit-two/phpcs.xml.dist', '<ruleset/>');
            self::writeFakePhpcs($dir . '/fake-phpcs');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--bin=' . $dir . '/fake-phpcs',
                '--processes=2',
            ], $dir);

            self::assertSame(2, $result['code']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testExplicitProjectDirectoryUsesNearestConfigAndPassesThroughToolOptions(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/packages/package-a/src', 0777, true);
            file_put_contents($dir . '/packages/package-a/phpcs.xml.dist', '<ruleset/>');
            self::writeFakePhpcs($dir . '/fake-phpcs');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--bin=' . $dir . '/fake-phpcs',
                'packages/package-a/src',
                '--',
                '-s',
                '--report=summary',
            ], $dir);

            self::assertSame(0, $result['code']);
            self::assertStringContainsString('FAKE package-a/phpcs.xml.dist', $result['stdout']);
            self::assertStringContainsString('packages/package-a/src', $result['stdout']);
            self::assertStringContainsString('PASSTHROUGH -s --report=summary', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testExplicitDirectoryConfigPrecedencePrefersDotPhpcsXml(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/package', 0777, true);
            file_put_contents($dir . '/package/.phpcs.xml', '<ruleset/>');
            file_put_contents($dir . '/package/phpcs.xml', '<ruleset/>');
            file_put_contents($dir . '/package/.phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/package/phpcs.xml.dist', '<ruleset/>');
            self::writeFakePhpcs($dir . '/fake-phpcs');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--bin=' . $dir . '/fake-phpcs',
                'package',
            ], $dir);

            self::assertSame(0, $result['code']);
            self::assertStringContainsString('STANDARD .phpcs.xml', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testConfigPatternSupportsDirectoryPatterns(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/plugins/plugin-a', 0777, true);
            mkdir($dir . '/themes/theme-a', 0777, true);
            mkdir($dir . '/tools/tool-a', 0777, true);
            file_put_contents($dir . '/plugins/plugin-a/phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/themes/theme-a/phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/tools/tool-a/phpcs.xml.dist', '<ruleset/>');
            self::writeFakePhpcs($dir . '/fake-phpcs');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--bin=' . $dir . '/fake-phpcs',
                '--config-pattern=plugins/*, themes/theme-a',
            ], $dir);

            self::assertSame(0, $result['code']);
            self::assertStringContainsString('FAKE plugin-a/phpcs.xml.dist', $result['stdout']);
            self::assertStringContainsString('FAKE theme-a/phpcs.xml.dist', $result['stdout']);
            self::assertStringNotContainsString('tool-a/phpcs.xml.dist', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testCommaSeparatedConfigPatternsAreSupported(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/plugins/plugin-a', 0777, true);
            mkdir($dir . '/themes/theme-a', 0777, true);
            mkdir($dir . '/tools/tool-a', 0777, true);
            file_put_contents($dir . '/plugins/plugin-a/phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/themes/theme-a/phpcs.xml.dist', '<ruleset/>');
            file_put_contents($dir . '/tools/tool-a/phpcs.xml.dist', '<ruleset/>');
            self::writeFakePhpcs($dir . '/fake-phpcs');

            $result = self::runCommand([
                $this->php,
                $this->bin,
                '--bin=' . $dir . '/fake-phpcs',
                '--config-pattern=plugins/*/phpcs.xml.dist, themes/*/phpcs.xml.dist',
            ], $dir);

            self::assertSame(0, $result['code']);
            self::assertStringContainsString('FAKE plugin-a/phpcs.xml.dist', $result['stdout']);
            self::assertStringContainsString('FAKE theme-a/phpcs.xml.dist', $result['stdout']);
            self::assertStringNotContainsString('tool-a/phpcs.xml.dist', $result['stdout']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testHelpOutputUsesPhpcbfWrapperName(): void
    {
        $result = self::runCommand([$this->php, $this->phpcbfBin, '--help'], $this->root);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString(
            'phpcbf-parallel - run phpcbf once per discovered PHPCS config.',
            $result['stdout']
        );
        self::assertStringContainsString('--bin=PATH            Path to the phpcbf binary.', $result['stdout']);
        self::assertStringContainsString('shell-style glob, not regex', $result['stdout']);
    }

    public function testNoDiscoveredConfigsReturnsError(): void
    {
        $dir = self::makeTmpDir();

        try {
            $result = self::runCommand([$this->php, $this->bin], $dir);

            self::assertSame(3, $result['code']);
            self::assertStringContainsString('No PHPCS config files found.', $result['stderr']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testExplicitDirectoryWithoutNearestConfigReturnsError(): void
    {
        $dir = self::makeTmpDir();

        try {
            mkdir($dir . '/package/src', 0777, true);

            $result = self::runCommand([$this->php, $this->bin, 'package/src'], $dir);

            self::assertSame(3, $result['code']);
            self::assertStringContainsString('No PHPCS config found for directory: package/src', $result['stderr']);
        } finally {
            self::rmrf($dir);
        }
    }

    public function testUnknownWrapperOptionReturnsErrorBeforeToolRuns(): void
    {
        $dir = self::makeTmpDir();

        try {
            $result = self::runCommand([$this->php, $this->bin, '--colors'], $dir);

            self::assertSame(3, $result['code']);
            self::assertStringContainsString(
                'Unknown phpcs-parallel option: --colors. Put PHPCS options after --.',
                $result['stderr']
            );
        } finally {
            self::rmrf($dir);
        }
    }

    public function testShortProcessOptionReturnsSpecificError(): void
    {
        $dir = self::makeTmpDir();

        try {
            $result = self::runCommand([$this->php, $this->bin, '-p'], $dir);

            self::assertSame(3, $result['code']);
            self::assertStringContainsString('Use --processes=N. Short -p is not supported yet.', $result['stderr']);
        } finally {
            self::rmrf($dir);
        }
    }

    private static function makeTmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/phpcs-parallel-test-' . bin2hex(random_bytes(6));
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

    private static function writeFakePhpcs(string $path): void
    {
        file_put_contents($path, <<<'PHP'
#!/usr/bin/env php
<?php
$config = '';
$configFile = '';
$root = '';
$passthrough = [];

foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--standard=')) {
		$configFile = substr($arg, 11);
		$config = basename(dirname($configFile)) . '/' . basename($configFile);
		continue;
	}

	if (is_dir($arg)) {
		$root = $arg;
		continue;
	}

	$passthrough[] = $arg;
}

$cwd = realpath(getcwd() ?: '') ?: (getcwd() ?: '');
$realRoot = realpath($root) ?: $root;
$relativeRoot = $realRoot;
if ($cwd !== '' && str_starts_with($realRoot, $cwd . DIRECTORY_SEPARATOR)) {
	$relativeRoot = substr($realRoot, strlen($cwd) + 1);
}

echo "FAKE $config\n";
echo 'STANDARD ' . basename($configFile) . "\n";
echo "ROOT $relativeRoot\n";
echo 'PASSTHROUGH ' . implode(' ', $passthrough) . "\n";
if (str_contains($config, 'exit-two')) {
	exit(2);
}
exit(0);
PHP);
        chmod($path, 0755);
    }
}

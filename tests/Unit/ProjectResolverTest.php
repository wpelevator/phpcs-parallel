<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use WPElevator\PHPCSParallel\ProjectResolver;

final class ProjectResolverTest extends TestCase
{
    private string $tmpDir;
    private ProjectResolver $resolver;

    protected function setUp(): void
    {
        $this->tmpDir = self::makeTmpDir();
        $this->resolver = new ProjectResolver();
    }

    protected function tearDown(): void
    {
        self::rmrf($this->tmpDir);
    }

    public function testExplicitDirectoryUsesOwnConfigBeforeNearestParentConfig(): void
    {
        $this->mkdir('package/src');
        $this->touchConfig('phpcs.xml.dist');
        $packageConfig = $this->touchConfig('package/.phpcs.xml');
        $this->touchConfig('package/phpcs.xml');

        $projects = $this->resolver->resolve([$this->path('package/src')], [], $this->tmpDir);

        self::assertCount(1, $projects);
        self::assertSame($this->realPath('package/src'), $projects[0]->rootDir);
        self::assertSame($packageConfig, $projects[0]->configFile);
    }

    public function testExplicitDirectoryFallsBackToNearestParentConfig(): void
    {
        $this->mkdir('package/src');
        $config = $this->touchConfig('package/phpcs.xml.dist');

        $projects = $this->resolver->resolve([$this->path('package/src')], [], $this->tmpDir);

        self::assertCount(1, $projects);
        self::assertSame($this->realPath('package/src'), $projects[0]->rootDir);
        self::assertSame($config, $projects[0]->configFile);
    }

    public function testDiscoveryFindsConfigsSortedByProjectRoot(): void
    {
        $configB = $this->touchConfig('packages/b/phpcs.xml.dist');
        $configA = $this->touchConfig('packages/a/phpcs.xml.dist');

        $projects = $this->resolver->resolve([], [], $this->tmpDir);

        self::assertSame(
            [$this->realPath('packages/a'), $this->realPath('packages/b')],
            array_column($projects, 'rootDir')
        );
        self::assertSame([$configA, $configB], array_column($projects, 'configFile'));
    }

    public function testDiscoveryCanBeFilteredWithRelativeOrAbsolutePatterns(): void
    {
        $pluginConfig = $this->touchConfig('plugins/plugin-a/phpcs.xml.dist');
        $themeConfig = $this->touchConfig('themes/theme-a/phpcs.xml.dist');
        $this->touchConfig('tools/tool-a/phpcs.xml.dist');

        $projects = $this->resolver->resolve(
            [],
            ['plugins/*/phpcs.xml.dist', $this->realPath('themes') . '/*/phpcs.xml.dist'],
            $this->tmpDir
        );

        self::assertSame([$pluginConfig, $themeConfig], array_column($projects, 'configFile'));
    }

    public function testDiscoverySkipsDependencyDirectories(): void
    {
        $appConfig = $this->touchConfig('app/phpcs.xml.dist');
        $this->touchConfig('.git/hooks/phpcs.xml.dist');
        $this->touchConfig('vendor/package/phpcs.xml.dist');
        $this->touchConfig('node_modules/package/phpcs.xml.dist');
        $this->touchConfig('bower_components/package/phpcs.xml.dist');

        $projects = $this->resolver->resolve([], [], $this->tmpDir);

        self::assertSame([$appConfig], array_column($projects, 'configFile'));
    }

    public function testExplicitProjectsAndDiscoveredProjectsAreDeduplicatedByRoot(): void
    {
        $config = $this->touchConfig('package/phpcs.xml.dist');

        $projects = $this->resolver->resolve([$this->path('package')], ['package/phpcs.xml.dist'], $this->tmpDir);

        self::assertCount(1, $projects);
        self::assertSame($config, $projects[0]->configFile);
    }

    public function testMissingExplicitDirectoryThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);

        $this->resolver->resolve([$this->path('missing')], [], $this->tmpDir);
    }

    public function testExplicitDirectoryWithoutAnyConfigThrowsRuntimeException(): void
    {
        $this->mkdir('package/src');
        $this->expectException(RuntimeException::class);

        $this->resolver->resolve([$this->path('package/src')], [], $this->tmpDir);
    }

    private function touchConfig(string $relativePath): string
    {
        $path = $this->path($relativePath);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, '<ruleset/>');

        return $this->realPath($relativePath);
    }

    private function mkdir(string $relativePath): void
    {
        mkdir($this->path($relativePath), 0777, true);
    }

    private function path(string $relativePath): string
    {
        return $this->tmpDir . '/' . $relativePath;
    }

    private function realPath(string $relativePath): string
    {
        $path = realpath($this->path($relativePath));
        self::assertIsString($path);

        return $path;
    }

    private static function makeTmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/phpcs-parallel-unit-' . bin2hex(random_bytes(6));
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
}

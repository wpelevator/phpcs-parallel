<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPElevator\PHPCSParallel\PathResolver;

final class PathResolverTest extends TestCase
{
    public function testResolvesSortedDeduplicatedRelativeTasksAndSkipsDependencyDirectories(): void
    {
        $dir = sys_get_temp_dir() . '/pharallel-path-' . bin2hex(random_bytes(6));
        mkdir($dir . '/packages/b', 0777, true);
        mkdir($dir . '/packages/a', 0777, true);
        mkdir($dir . '/vendor/c', 0777, true);
        file_put_contents($dir . '/packages/b/phpstan.neon', '');
        file_put_contents($dir . '/packages/a/phpstan.neon', '');
        file_put_contents($dir . '/vendor/c/phpstan.neon', '');

        try {
            $tasks = (new PathResolver())->resolve([
                'packages/*/phpstan.neon',
                'packages/a/phpstan.neon',
                'vendor/*/phpstan.neon',
            ], $dir);

            self::assertSame('packages/a/phpstan.neon', $tasks[0]->path);
            self::assertSame(0, $tasks[0]->index);
            self::assertSame('packages/b/phpstan.neon', $tasks[1]->path);
            self::assertSame(1, $tasks[1]->index);
            self::assertCount(2, $tasks);
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

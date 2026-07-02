<?php

declare(strict_types=1);

namespace WPElevator\RunParallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\RunParallel\PathResolver;

final class PathResolverTest extends TestCase
{
    private TempWorkspace $workspace;

    protected function setUp(): void
    {
        $this->workspace = new TempWorkspace('run-parallel-path');
    }

    protected function tearDown(): void
    {
        $this->workspace->cleanup();
    }

    public function testResolvesSortedDeduplicatedRelativeTasksAndSkipsDependencyDirectories(): void
    {
        $this->workspace->writeFile('packages/b/phpstan.neon', '');
        $this->workspace->writeFile('packages/a/phpstan.neon', '');
        $this->workspace->writeFile('vendor/c/phpstan.neon', '');

        $tasks = (new PathResolver())->resolve([
            'packages/*/phpstan.neon',
            'packages/a/phpstan.neon',
            'vendor/*/phpstan.neon',
        ], $this->workspace->dir);

        $this->assertSame('packages/a/phpstan.neon', $tasks[0]->path);
        $this->assertSame(0, $tasks[0]->index);
        $this->assertSame('packages/b/phpstan.neon', $tasks[1]->path);
        $this->assertSame(1, $tasks[1]->index);
        $this->assertCount(2, $tasks);
    }

    public function testSingleStarMatchesOneLevelWhileDoubleStarMatchesDeep(): void
    {
        $this->workspace->writeFile('packages/a/phpstan.neon', '');
        $this->workspace->writeFile('packages/a/nested/phpstan.neon', '');

        $shallow = (new PathResolver())->resolve(['packages/*/phpstan.neon'], $this->workspace->dir);
        $this->assertCount(1, $shallow);
        $this->assertSame('packages/a/phpstan.neon', $shallow[0]->path);

        $deep = (new PathResolver())->resolve(['packages/**/phpstan.neon'], $this->workspace->dir);
        $this->assertCount(2, $deep);
        $this->assertSame('packages/a/nested/phpstan.neon', $deep[0]->path);
        $this->assertSame('packages/a/phpstan.neon', $deep[1]->path);
    }
}

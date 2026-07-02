<?php

declare(strict_types=1);

namespace WPElevator\Pharallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\Pharallel\PathResolver;

final class PathResolverTest extends TestCase
{
    private TempWorkspace $workspace;

    protected function setUp(): void
    {
        $this->workspace = new TempWorkspace('pharallel-path');
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

        self::assertSame('packages/a/phpstan.neon', $tasks[0]->path);
        self::assertSame(0, $tasks[0]->index);
        self::assertSame('packages/b/phpstan.neon', $tasks[1]->path);
        self::assertSame(1, $tasks[1]->index);
        self::assertCount(2, $tasks);
    }

    public function testSingleStarMatchesOneLevelWhileDoubleStarMatchesDeep(): void
    {
        $this->workspace->writeFile('packages/a/phpstan.neon', '');
        $this->workspace->writeFile('packages/a/nested/phpstan.neon', '');

        $shallow = (new PathResolver())->resolve(['packages/*/phpstan.neon'], $this->workspace->dir);
        self::assertCount(1, $shallow);
        self::assertSame('packages/a/phpstan.neon', $shallow[0]->path);

        $deep = (new PathResolver())->resolve(['packages/**/phpstan.neon'], $this->workspace->dir);
        self::assertCount(2, $deep);
        self::assertSame('packages/a/nested/phpstan.neon', $deep[0]->path);
        self::assertSame('packages/a/phpstan.neon', $deep[1]->path);
    }
}

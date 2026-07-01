<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\PHPCSParallel\Task;
use WPElevator\PHPCSParallel\TemplateRenderer;

final class TemplateRendererTest extends TestCase
{
    public function testRendersVariablesAndFiltersRelativeToInvocationCwd(): void
    {
        $renderer = new TemplateRenderer();
        $task = new Task('packages/foo/phpstan.neon', 2);

        self::assertSame('packages/foo', $renderer->render('{path | dirname}', $task, '/repo'));
        self::assertSame('foo', $renderer->render('{path | dirname | basename}', $task, '/repo'));
        self::assertSame('phpstan', $renderer->render('{path | filename}', $task, '/repo'));
        self::assertSame('2', $renderer->render('{index}', $task, '/repo'));
    }

    public function testRendersExtraTaskVariables(): void
    {
        $renderer = new TemplateRenderer();
        $task = new Task('/repo/phpcs.xml.dist', 0, ['root' => '/repo/packages/foo']);

        self::assertSame('/repo/packages/foo', $renderer->render('{root}', $task, '/repo'));
    }

    public function testRendersConfiguredVariablesAndFilters(): void
    {
        $renderer = new TemplateRenderer(
            ['package' => static fn (string $path): string => basename(dirname($path))],
            ['php' => PHP_BINARY]
        );
        $task = new Task('packages/foo/composer.json', 0);

        self::assertSame(PHP_BINARY, $renderer->render('{php}', $task, '/repo'));
        self::assertSame('foo', $renderer->render('{path | package}', $task, '/repo'));
    }
}

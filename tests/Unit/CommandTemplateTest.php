<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\PHPCSParallel\CommandTemplate;
use WPElevator\PHPCSParallel\Task;
use WPElevator\PHPCSParallel\TemplateRenderer;

final class CommandTemplateTest extends TestCase
{
    public function testRendersCommandAsArgv(): void
    {
        $template = new CommandTemplate(new TemplateRenderer());
        $task = new Task('packages/foo/phpstan.neon', 0);

        $command = $template->render(
            'phpstan analyse --configuration={path | realpath} {path | dirname} --memory-limit=1G',
            $task,
            '/repo'
        );

        self::assertSame([
            'phpstan',
            'analyse',
            '--configuration=/repo/packages/foo/phpstan.neon',
            'packages/foo',
            '--memory-limit=1G',
        ], $command);
    }
}

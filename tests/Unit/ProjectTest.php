<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\PHPCSParallel\Project;

final class ProjectTest extends TestCase
{
    public function testLabelUsesBasenameOfProjectRoot(): void
    {
        $project = new Project('/repo/plugins/plugin-a', '/repo/plugins/plugin-a/phpcs.xml');

        self::assertSame('plugin-a', $project->label());
    }
}

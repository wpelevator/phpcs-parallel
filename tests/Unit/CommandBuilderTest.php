<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\PHPCSParallel\CommandBuilder;
use WPElevator\PHPCSParallel\Project;

final class CommandBuilderTest extends TestCase
{
    public function testBuildsToolCommandWithPassthroughStandardAndRootDirectory(): void
    {
        $project = new Project('/repo/packages/foo', '/repo/packages/foo/phpcs.xml.dist');

        $command = (new CommandBuilder())->build('/repo/vendor/bin/phpcs', $project, ['-s', '--report=summary']);

        self::assertSame([
            '/repo/vendor/bin/phpcs',
            '-s',
            '--report=summary',
            '--standard=/repo/packages/foo/phpcs.xml.dist',
            '/repo/packages/foo',
        ], $command);
    }
}

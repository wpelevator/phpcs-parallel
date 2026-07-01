<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPElevator\PHPCSParallel\ArgsParser;

final class ArgsParserTest extends TestCase
{
    private ArgsParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ArgsParser();
    }

    public function testParsesWrapperOptionsDirectoriesAndPassthroughArguments(): void
    {
        $options = $this->parser->parse([
            '--bin=/tools/phpcs',
            '--processes=0',
            '--config-pattern=packages/*/phpcs.xml.dist, apps/*/phpcs.xml.dist',
            'packages/foo',
            '--',
            '-s',
            '--report=summary',
        ]);

        self::assertSame('/tools/phpcs', $options->bin);
        self::assertSame(1, $options->processes);
        self::assertSame(['packages/*/phpcs.xml.dist', 'apps/*/phpcs.xml.dist'], $options->configPatterns);
        self::assertSame(['packages/foo'], $options->dirs);
        self::assertSame(['-s', '--report=summary'], $options->passthrough);
        self::assertFalse($options->help);
    }

    public function testUsesSafeDefaultsWhenNoArgumentsAreProvided(): void
    {
        $options = $this->parser->parse([]);

        self::assertSame([], $options->dirs);
        self::assertSame([], $options->configPatterns);
        self::assertSame(1, $options->processes);
        self::assertSame([], $options->passthrough);
        self::assertNull($options->bin);
        self::assertFalse($options->help);
    }

    public function testParsesLongAndShortHelp(): void
    {
        self::assertTrue($this->parser->parse(['--help'])->help);
        self::assertTrue($this->parser->parse(['-h'])->help);
    }

    public function testMergesRepeatedAndCommaSeparatedConfigPatterns(): void
    {
        $options = $this->parser->parse([
            '--config-pattern=packages/*/phpcs.xml.dist, apps/*/phpcs.xml.dist',
            '--config-pattern=tools/*/phpcs.xml',
        ]);

        self::assertSame(
            ['packages/*/phpcs.xml.dist', 'apps/*/phpcs.xml.dist', 'tools/*/phpcs.xml'],
            $options->configPatterns
        );
    }

    public function testSeparatorAllowsToolOptionsThatLookLikeWrapperOptions(): void
    {
        $options = $this->parser->parse(['packages/foo', '--', '--colors', '--processes=99']);

        self::assertSame(['packages/foo'], $options->dirs);
        self::assertSame(['--colors', '--processes=99'], $options->passthrough);
        self::assertSame(1, $options->processes);
    }

    public function testRejectsShortProcessOption(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse(['-p']);
    }

    public function testRejectsUnknownWrapperOption(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse(['--colors']);
    }
}

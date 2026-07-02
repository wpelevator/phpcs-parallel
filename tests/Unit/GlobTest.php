<?php

declare(strict_types=1);

namespace WPElevator\Pharallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\Pharallel\Glob;

final class GlobTest extends TestCase
{
    public function testSingleStarDoesNotCrossDirectorySeparator(): void
    {
        $regex = Glob::toRegex('packages/*/phpcs.xml.dist');

        self::assertSame(1, preg_match($regex, 'packages/foo/phpcs.xml.dist'));
        self::assertSame(0, preg_match($regex, 'packages/foo/nested/phpcs.xml.dist'));
    }

    public function testDoubleStarCrossesDirectorySeparators(): void
    {
        $regex = Glob::toRegex('packages/**/phpcs.xml.dist');

        self::assertSame(1, preg_match($regex, 'packages/phpcs.xml.dist'));
        self::assertSame(1, preg_match($regex, 'packages/foo/phpcs.xml.dist'));
        self::assertSame(1, preg_match($regex, 'packages/foo/nested/phpcs.xml.dist'));
        self::assertSame(0, preg_match($regex, 'other/foo/phpcs.xml.dist'));
    }

    public function testLeadingDoubleStarMatchesAtAnyDepth(): void
    {
        $regex = Glob::toRegex('**/phpcs.xml');

        self::assertSame(1, preg_match($regex, 'phpcs.xml'));
        self::assertSame(1, preg_match($regex, 'a/b/phpcs.xml'));
    }

    public function testQuestionMarkMatchesSingleCharacterWithinSegment(): void
    {
        $regex = Glob::toRegex('a?c');

        self::assertSame(1, preg_match($regex, 'abc'));
        self::assertSame(0, preg_match($regex, 'a/c'));
        self::assertSame(0, preg_match($regex, 'abbc'));
    }

    public function testCharacterClassAndNegation(): void
    {
        $regex = Glob::toRegex('file[0-9].txt');
        self::assertSame(1, preg_match($regex, 'file1.txt'));
        self::assertSame(0, preg_match($regex, 'filex.txt'));

        $negated = Glob::toRegex('file[!0-9].txt');
        self::assertSame(1, preg_match($negated, 'filex.txt'));
        self::assertSame(0, preg_match($negated, 'file1.txt'));
    }

    public function testUnterminatedBracketIsLiteral(): void
    {
        $regex = Glob::toRegex('a[bc');

        self::assertSame(1, preg_match($regex, 'a[bc'));
        self::assertSame(0, preg_match($regex, 'ab'));
    }

    public function testLiteralRegexCharactersAreEscaped(): void
    {
        $regex = Glob::toRegex('packages/a.b/composer.json');

        self::assertSame(1, preg_match($regex, 'packages/a.b/composer.json'));
        self::assertSame(0, preg_match($regex, 'packages/axb/composer.json'));
    }
}

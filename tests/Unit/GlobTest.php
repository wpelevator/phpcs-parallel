<?php

declare(strict_types=1);

namespace WPElevator\RunParallel\Tests;

use PHPUnit\Framework\TestCase;
use WPElevator\RunParallel\Glob;

final class GlobTest extends TestCase
{
    public function testSingleStarDoesNotCrossDirectorySeparator(): void
    {
        $regex = Glob::toRegex('packages/*/phpcs.xml.dist');

        $this->assertSame(1, preg_match($regex, 'packages/foo/phpcs.xml.dist'));
        $this->assertSame(0, preg_match($regex, 'packages/foo/nested/phpcs.xml.dist'));
    }

    public function testDoubleStarCrossesDirectorySeparators(): void
    {
        $regex = Glob::toRegex('packages/**/phpcs.xml.dist');

        $this->assertSame(1, preg_match($regex, 'packages/phpcs.xml.dist'));
        $this->assertSame(1, preg_match($regex, 'packages/foo/phpcs.xml.dist'));
        $this->assertSame(1, preg_match($regex, 'packages/foo/nested/phpcs.xml.dist'));
        $this->assertSame(0, preg_match($regex, 'other/foo/phpcs.xml.dist'));
    }

    public function testLeadingDoubleStarMatchesAtAnyDepth(): void
    {
        $regex = Glob::toRegex('**/phpcs.xml');

        $this->assertSame(1, preg_match($regex, 'phpcs.xml'));
        $this->assertSame(1, preg_match($regex, 'a/b/phpcs.xml'));
    }

    public function testQuestionMarkMatchesSingleCharacterWithinSegment(): void
    {
        $regex = Glob::toRegex('a?c');

        $this->assertSame(1, preg_match($regex, 'abc'));
        $this->assertSame(0, preg_match($regex, 'a/c'));
        $this->assertSame(0, preg_match($regex, 'abbc'));
    }

    public function testCharacterClassAndNegation(): void
    {
        $regex = Glob::toRegex('file[0-9].txt');
        $this->assertSame(1, preg_match($regex, 'file1.txt'));
        $this->assertSame(0, preg_match($regex, 'filex.txt'));

        $negated = Glob::toRegex('file[!0-9].txt');
        $this->assertSame(1, preg_match($negated, 'filex.txt'));
        $this->assertSame(0, preg_match($negated, 'file1.txt'));
    }

    public function testUnterminatedBracketIsLiteral(): void
    {
        $regex = Glob::toRegex('a[bc');

        $this->assertSame(1, preg_match($regex, 'a[bc'));
        $this->assertSame(0, preg_match($regex, 'ab'));
    }

    public function testLiteralRegexCharactersAreEscaped(): void
    {
        $regex = Glob::toRegex('packages/a.b/composer.json');

        $this->assertSame(1, preg_match($regex, 'packages/a.b/composer.json'));
        $this->assertSame(0, preg_match($regex, 'packages/axb/composer.json'));
    }
}

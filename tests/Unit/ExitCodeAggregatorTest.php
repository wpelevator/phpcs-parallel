<?php

declare(strict_types=1);

namespace WPElevator\PHPCSParallel\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPElevator\PHPCSParallel\ExitCodeAggregator;

final class ExitCodeAggregatorTest extends TestCase
{
    #[DataProvider('exitCodes')]
    public function testAggregatesExitCodes(int $current, int $next, int $expected): void
    {
        self::assertSame($expected, (new ExitCodeAggregator())->aggregate($current, $next));
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function exitCodes(): iterable
    {
        yield 'keeps success' => [0, 0, 0];
        yield 'returns highest lint failure' => [1, 2, 2];
        yield 'promotes child runtime errors to at least three' => [0, 3, 3];
        yield 'keeps higher runtime errors' => [3, 4, 4];
        yield 'keeps previous runtime error when next is lower' => [3, 1, 3];
    }
}

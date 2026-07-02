<?php

namespace WPElevator\RunParallel;

final class Processes
{
    public static function parse(string $value): int
    {
        if (trim($value) === 'auto') {
            return CpuCount::detect();
        }

        return max(1, (int) $value);
    }
}

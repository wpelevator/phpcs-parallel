<?php

namespace WPElevator\Pharallel;

final class CpuCount
{
    public static function detect(): int
    {
        $env = getenv('NUMBER_OF_PROCESSORS');
        if (is_string($env) && (int) $env > 0) {
            return (int) $env;
        }

        if (is_readable('/proc/cpuinfo')) {
            $count = preg_match_all('/^processor\s*:/m', (string) file_get_contents('/proc/cpuinfo'));
            if (is_int($count) && $count > 0) {
                return $count;
            }
        }

        if (function_exists('shell_exec')) {
            $output = shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if (is_string($output) && (int) trim($output) > 0) {
                return (int) trim($output);
            }
        }

        return 1;
    }
}

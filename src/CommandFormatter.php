<?php

namespace WPElevator\Pharallel;

final class CommandFormatter
{
    /** @param list<string> $command */
    public static function format(array $command): string
    {
        return implode(' ', array_map(static function (string $arg): string {
            if ($arg !== '' && preg_match('#^[A-Za-z0-9@%+=:,./_-]+$#', $arg) === 1) {
                return $arg;
            }

            return escapeshellarg($arg);
        }, $command));
    }
}

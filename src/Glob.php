<?php

namespace WPElevator\RunParallel;

final class Glob
{
    /**
     * Converts a glob pattern to a regular expression. Unlike fnmatch(),
     * `*` and `?` never match a directory separator; `**` matches any
     * number of path segments.
     */
    public static function toRegex(string $pattern): string
    {
        $regex = '';
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            if ($char === '*') {
                if (($pattern[$i + 1] ?? '') === '*') {
                    $i++;
                    if (($pattern[$i + 1] ?? '') === '/') {
                        $i++;
                        $regex .= '(?:[^/]+/)*';
                    } else {
                        $regex .= '.*';
                    }
                } else {
                    $regex .= '[^/]*';
                }
                continue;
            }

            if ($char === '?') {
                $regex .= '[^/]';
                continue;
            }

            if ($char === '[') {
                $class = self::characterClass($pattern, $i);
                if ($class !== null) {
                    [$classRegex, $i] = $class;
                    $regex .= $classRegex;
                    continue;
                }
            }

            $regex .= preg_quote($char, '#');
        }

        return '#^' . $regex . '$#';
    }

    /**
     * Parses a `[...]` character class starting at $start. Returns the
     * regex fragment and the offset of the closing bracket, or null when
     * the class is empty or unterminated (the `[` is then literal).
     *
     * @return array{string, int}|null
     */
    private static function characterClass(string $pattern, int $start): ?array
    {
        $length = strlen($pattern);
        $i = $start + 1;
        $negate = false;

        if (($pattern[$i] ?? '') === '!' || ($pattern[$i] ?? '') === '^') {
            $negate = true;
            $i++;
        }

        $content = '';
        if (($pattern[$i] ?? '') === ']') {
            $content .= '\\]';
            $i++;
        }

        while ($i < $length && $pattern[$i] !== ']') {
            $char = $pattern[$i];
            $content .= in_array($char, ['\\', '^', ']', '#'], true) ? '\\' . $char : $char;
            $i++;
        }

        if ($i >= $length || $content === '') {
            return null;
        }

        return ['[' . ($negate ? '^' : '') . $content . ']', $i];
    }
}

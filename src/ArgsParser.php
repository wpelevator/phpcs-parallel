<?php

namespace WPElevator\PHPCSParallel;

final class ArgsParser
{
    /** @param list<string> $args */
    public function parse(array $args): CliOptions
    {
        $dirs = [];
        $configPatterns = [];
        $processes = 1;
        $bin = null;
        $help = false;
        $passthrough = [];

        $separator = array_search('--', $args, true);
        if ($separator !== false) {
            $passthrough = array_slice($args, $separator + 1);
            $args = array_slice($args, 0, $separator);
        }

        foreach ($args as $arg) {
            if ($arg === '-h' || $arg === '--help') {
                $help = true;
                continue;
            }

            if (str_starts_with($arg, '--config-pattern=')) {
                $configPatterns = array_merge($configPatterns, $this->splitCsv(substr($arg, 17)));
                continue;
            }

            if (str_starts_with($arg, '--processes=')) {
                $processes = max(1, (int) substr($arg, 12));
                continue;
            }

            if ($arg === '-p') {
                throw new \InvalidArgumentException('Use --processes=N. Short -p is not supported yet.');
            }

            if (str_starts_with($arg, '--bin=')) {
                $bin = substr($arg, 6);
                continue;
            }

            if (str_starts_with($arg, '-')) {
                throw new \InvalidArgumentException(
                    'Unknown phpcs-parallel option: ' . $arg . '. Put PHPCS options after --.'
                );
            }

            $dirs[] = $arg;
        }

        return new CliOptions($dirs, $configPatterns, $processes, $passthrough, $bin, $help);
    }

    /** @return list<string> */
    private function splitCsv(string $value): array
    {
        $items = [];
        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }
}

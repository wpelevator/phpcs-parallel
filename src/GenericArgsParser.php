<?php

namespace WPElevator\PHPCSParallel;

final class GenericArgsParser
{
    /** @param list<string> $args */
    public function parse(array $args): GenericCliOptions
    {
        $pathPatterns = [];
        $command = null;
        $processes = null;
        $cwdTemplate = null;
        $labelTemplate = null;
        $configPath = null;
        $help = false;

        foreach ($args as $arg) {
            if ($arg === '-h' || $arg === '--help') {
                $help = true;
                continue;
            }

            if (str_starts_with($arg, '--path-pattern=')) {
                $pathPatterns = array_merge($pathPatterns, $this->splitCsv(substr($arg, 15)));
                continue;
            }

            if (str_starts_with($arg, '--command=')) {
                $command = substr($arg, 10);
                continue;
            }

            if (str_starts_with($arg, '--processes=')) {
                $processes = max(1, (int) substr($arg, 12));
                continue;
            }

            if (str_starts_with($arg, '--cwd=')) {
                $cwdTemplate = substr($arg, 6);
                continue;
            }

            if (str_starts_with($arg, '--label=')) {
                $labelTemplate = substr($arg, 8);
                continue;
            }

            if (str_starts_with($arg, '--config=')) {
                $configPath = substr($arg, 9);
                continue;
            }

            if ($arg === '-p') {
                throw new \InvalidArgumentException('Use --processes=N. Short -p is not supported yet.');
            }

            if (str_starts_with($arg, '-')) {
                throw new \InvalidArgumentException(
                    'Unknown pharallel option: ' . $arg . '. Put command options inside --command.'
                );
            }

            throw new \InvalidArgumentException('Unexpected argument: ' . $arg . '. Use --path-pattern=GLOB.');
        }

        return new GenericCliOptions(
            $pathPatterns,
            $command,
            $processes,
            $cwdTemplate,
            $labelTemplate,
            $configPath,
            $help
        );
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

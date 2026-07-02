<?php

namespace WPElevator\RunParallel;

final class PathResolver
{
    /**
     * @param list<string> $patterns
     * @return list<Task>
     */
    public function resolve(array $patterns, string $cwd): array
    {
        if ($patterns === []) {
            throw new \InvalidArgumentException('At least one --path-pattern is required.');
        }

        $root = $this->realDirectory($cwd);
        $matches = [];

        foreach ($patterns as $pattern) {
            foreach ($this->findMatches($root, $pattern) as $path) {
                $matches[$path] = $path;
            }
        }

        ksort($matches, SORT_STRING);

        $tasks = [];
        $index = 0;
        foreach (array_values($matches) as $path) {
            $tasks[] = new Task($this->relativeToCwd($path, $root), $index++);
        }

        return $tasks;
    }

    private function realDirectory(string $dir): string
    {
        $real = realpath($dir);
        if ($real === false || ! is_dir($real)) {
            throw new \RuntimeException('Directory does not exist: ' . $dir);
        }

        return $real;
    }

    /** @return list<string> */
    private function findMatches(string $root, string $pattern): array
    {
        $matches = [];
        $normalizedPattern = rtrim(str_replace('\\', '/', $pattern), '/');
        $regex = Glob::toRegex($normalizedPattern);
        $matchAbsolute = Path::isAbsolute($normalizedPattern);

        foreach ($this->candidatePaths($root) as $path) {
            $normalizedPath = str_replace('\\', '/', $path);
            $subject = $matchAbsolute ? $normalizedPath : $this->relativeToCwd($normalizedPath, $root);

            if (preg_match($regex, $subject) === 1) {
                $matches[] = $path;
            }
        }

        sort($matches, SORT_STRING);
        return $matches;
    }

    /** @return \Generator<int, string> */
    private function candidatePaths(string $root): \Generator
    {
        yield $root;

        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, $flags),
                static function (\SplFileInfo $file): bool {
                    if (! $file->isDir()) {
                        return true;
                    }

                    return ! in_array(
                        $file->getFilename(),
                        ['.git', 'vendor', 'node_modules', 'bower_components'],
                        true
                    );
                }
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo) {
                yield $file->getPathname();
            }
        }
    }

    private function relativeToCwd(string $path, string $cwd): string
    {
        $path = str_replace('\\', '/', $path);
        $cwd = rtrim(str_replace('\\', '/', $cwd), '/');
        if ($path === $cwd) {
            return '.';
        }
        if ($cwd !== '' && str_starts_with($path, $cwd . '/')) {
            return substr($path, strlen($cwd) + 1);
        }

        return $path;
    }
}

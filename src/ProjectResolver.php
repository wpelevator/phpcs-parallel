<?php

namespace WPElevator\PHPCSParallel;

final class ProjectResolver
{
    private const CONFIG_NAMES = [
        '.phpcs.xml',
        'phpcs.xml',
        '.phpcs.xml.dist',
        'phpcs.xml.dist',
    ];

    /**
     * @param list<string> $dirs
     * @param list<string> $configPatterns
     * @return list<Project>
     */
    public function resolve(array $dirs, array $configPatterns, string $cwd): array
    {
        $projects = [];

        foreach ($dirs as $dir) {
            $root = $this->realDirectory($dir);
            $config = $this->findConfigInDirectory($root) ?? $this->findNearestConfig($root);
            if ($config === null) {
                throw new \RuntimeException('No PHPCS config found for directory: ' . $dir);
            }
            $projects[$root] = new Project($root, $config);
        }

        if ($dirs === [] || $configPatterns !== []) {
            $discoveryRoot = $this->realDirectory($cwd);
            foreach ($this->discoverConfigs($discoveryRoot, $configPatterns, $discoveryRoot) as $config) {
                $projectRoot = dirname($config);
                $projects[$projectRoot] = new Project($projectRoot, $config);
            }
        }

        ksort($projects, SORT_STRING);
        return array_values($projects);
    }

    private function realDirectory(string $dir): string
    {
        $real = realpath($dir);
        if ($real === false || ! is_dir($real)) {
            throw new \RuntimeException('Directory does not exist: ' . $dir);
        }

        return $real;
    }

    private function findConfigInDirectory(string $dir): ?string
    {
        foreach (self::CONFIG_NAMES as $name) {
            $file = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    private function findNearestConfig(string $dir): ?string
    {
        $current = $dir;
        while (true) {
            $config = $this->findConfigInDirectory($current);
            if ($config !== null) {
                return $config;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                return null;
            }
            $current = $parent;
        }
    }

    /**
     * @param list<string> $configPatterns
     * @return list<string>
     */
    private function discoverConfigs(string $root, array $configPatterns, string $cwd): array
    {
        $configs = [];
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
            )
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }
            if (! in_array($file->getFilename(), self::CONFIG_NAMES, true)) {
                continue;
            }

            $path = $file->getPathname();
            if ($configPatterns !== [] && ! $this->matchesAnyConfigPattern($path, $configPatterns, $cwd)) {
                continue;
            }

            $configs[] = $path;
        }

        sort($configs, SORT_STRING);
        return $configs;
    }

    /** @param list<string> $patterns */
    private function matchesAnyConfigPattern(string $path, array $patterns, string $cwd): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedRoot = str_replace('\\', '/', dirname($path));
        $relativePath = $this->relativeToCwd($normalizedPath, $cwd);
        $relativeRoot = $this->relativeToCwd($normalizedRoot, $cwd);

        foreach ($patterns as $pattern) {
            $normalizedPattern = rtrim(str_replace('\\', '/', $pattern), '/');
            if (
                fnmatch($normalizedPattern, $relativePath)
                || fnmatch($normalizedPattern, $normalizedPath)
                || fnmatch($normalizedPattern, $relativeRoot)
                || fnmatch($normalizedPattern, $normalizedRoot)
            ) {
                return true;
            }
        }

        return false;
    }

    private function relativeToCwd(string $path, string $cwd): string
    {
        $cwd = str_replace('\\', '/', $cwd);
        if ($cwd !== '' && str_starts_with($path, rtrim($cwd, '/') . '/')) {
            return substr($path, strlen(rtrim($cwd, '/')) + 1);
        }

        return $path;
    }
}

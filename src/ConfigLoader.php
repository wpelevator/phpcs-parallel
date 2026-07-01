<?php

namespace WPElevator\PHPCSParallel;

final class ConfigLoader
{
    public function load(?string $path, string $cwd): PharallelConfig
    {
        if ($path === null || $path === '') {
            return new PharallelConfig();
        }

        $configPath = $this->isAbsolutePath($path) ? $path : $cwd . DIRECTORY_SEPARATOR . $path;
        if (! is_file($configPath)) {
            throw new \RuntimeException('Config file does not exist: ' . $path);
        }

        $data = require $configPath;
        if (! is_array($data)) {
            throw new \RuntimeException('Config file must return an array: ' . $path);
        }

        return new PharallelConfig(
            $this->loadFilters($data['filters'] ?? []),
            $this->loadVariables($data['variables'] ?? []),
            $this->loadDefaults($data['defaults'] ?? [])
        );
    }

    /**
     * @param mixed $filters
     * @return array<string, callable(string, Task, string): string>
     */
    private function loadFilters(mixed $filters): array
    {
        if (! is_array($filters)) {
            throw new \RuntimeException('Config "filters" must be an array.');
        }

        $loaded = [];
        foreach ($filters as $name => $filter) {
            if (! is_string($name) || $name === '') {
                throw new \RuntimeException('Config filter names must be non-empty strings.');
            }
            if (! is_callable($filter)) {
                throw new \RuntimeException('Config filter must be callable: ' . $name);
            }

            $loaded[$name] = static function (string $value, Task $task, string $cwd) use ($filter): string {
                $result = $filter($value, $task, $cwd);
                if (! is_scalar($result) && ! $result instanceof \Stringable) {
                    throw new \RuntimeException('Config filter must return a scalar or stringable value.');
                }

                return (string) $result;
            };
        }

        return $loaded;
    }

    /**
     * @param mixed $variables
     * @return array<string, string>
     */
    private function loadVariables(mixed $variables): array
    {
        if (! is_array($variables)) {
            throw new \RuntimeException('Config "variables" must be an array.');
        }

        $loaded = [];
        foreach ($variables as $name => $value) {
            if (! is_string($name) || $name === '') {
                throw new \RuntimeException('Config variable names must be non-empty strings.');
            }
            if (! is_scalar($value) && ! $value instanceof \Stringable) {
                throw new \RuntimeException('Config variable must be scalar or stringable: ' . $name);
            }

            $loaded[$name] = (string) $value;
        }

        return $loaded;
    }

    /**
     * @param mixed $defaults
     * @return array<string, mixed>
     */
    private function loadDefaults(mixed $defaults): array
    {
        if (! is_array($defaults)) {
            throw new \RuntimeException('Config "defaults" must be an array.');
        }

        $loaded = [];
        foreach ($defaults as $name => $value) {
            if (! is_string($name) || $name === '') {
                throw new \RuntimeException('Config default names must be non-empty strings.');
            }

            $loaded[$name] = $value;
        }

        return $loaded;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}

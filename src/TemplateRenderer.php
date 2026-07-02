<?php

namespace WPElevator\Pharallel;

final class TemplateRenderer
{
    /**
     * @param array<string, callable(string, Task, string): string> $customFilters
     * @param array<string, string> $variables
     */
    public function __construct(
        private readonly array $customFilters = [],
        private readonly array $variables = [],
    ) {
    }

    public function render(string $template, Task $task, string $invocationCwd): string
    {
        return preg_replace_callback('/\{([^{}]+)\}/', function (array $matches) use ($task, $invocationCwd): string {
            return $this->renderExpression(trim($matches[1]), $task, $invocationCwd);
        }, $template) ?? $template;
    }

    public function renderExpression(string $expression, Task $task, string $invocationCwd): string
    {
        $parts = array_map('trim', explode('|', $expression));
        $variable = array_shift($parts);
        if ($variable === '') {
            throw new \InvalidArgumentException('Empty template expression.');
        }

        $variables = array_merge($this->variables, $task->variables());
        if (! array_key_exists($variable, $variables)) {
            throw new \InvalidArgumentException('Unknown template variable: ' . $variable);
        }

        $value = $variables[$variable];
        foreach ($parts as $filter) {
            if ($filter === '') {
                continue;
            }
            $value = $this->applyFilter($value, $filter, $task, $invocationCwd);
        }

        return $value;
    }

    private function applyFilter(string $value, string $filter, Task $task, string $invocationCwd): string
    {
        if (array_key_exists($filter, $this->customFilters)) {
            return ($this->customFilters[$filter])($value, $task, $invocationCwd);
        }

        return match ($filter) {
            'dirname' => dirname($value),
            'basename' => basename($value),
            'realpath' => $this->realpathFilter($value, $invocationCwd),
            'relative' => $this->relativeFilter($value, $invocationCwd),
            'slug' => $this->slugFilter($value),
            'ext' => pathinfo($value, PATHINFO_EXTENSION),
            'filename' => pathinfo($value, PATHINFO_FILENAME),
            default => throw new \InvalidArgumentException('Unknown template filter: ' . $filter),
        };
    }

    private function realpathFilter(string $value, string $invocationCwd): string
    {
        $candidate = Path::isAbsolute($value) ? $value : $invocationCwd . DIRECTORY_SEPARATOR . $value;
        $real = realpath($candidate);

        return $real !== false ? $real : $candidate;
    }

    private function relativeFilter(string $value, string $invocationCwd): string
    {
        $path = str_replace('\\', '/', $this->realpathFilter($value, $invocationCwd));
        $cwd = rtrim(str_replace('\\', '/', $invocationCwd), '/');
        if ($path === $cwd) {
            return '.';
        }
        if ($cwd !== '' && str_starts_with($path, $cwd . '/')) {
            return substr($path, strlen($cwd) + 1);
        }

        return $value;
    }

    private function slugFilter(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? $value;
        return trim($slug, '-');
    }
}

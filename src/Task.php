<?php

namespace WPElevator\RunParallel;

final class Task
{
    /** @param array<string, string> $variables */
    public function __construct(
        public readonly string $path,
        public readonly int $index,
        public readonly array $variables = [],
        public readonly ?string $command = null,
    ) {
    }

    /** @return array<string, string> */
    public function variables(): array
    {
        $variables = array_merge($this->variables, [
            'path' => $this->path,
            'index' => (string) $this->index,
        ]);

        if ($this->command !== null) {
            $variables['command'] = $this->command;
        }

        return $variables;
    }
}

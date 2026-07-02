<?php

namespace WPElevator\RunParallel;

final class RunParallelConfig
{
    /**
     * @param array<string, callable(string, Task, string): string> $filters
     * @param array<string, string> $variables
     * @param array<string, mixed> $defaults
     */
    public function __construct(
        public readonly array $filters = [],
        public readonly array $variables = [],
        public readonly array $defaults = [],
    ) {
    }
}

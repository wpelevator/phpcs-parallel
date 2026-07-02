<?php

namespace WPElevator\RunParallel;

final class CliOptions
{
    /**
     * @param list<string> $pathPatterns
     * @param list<string> $commands
     */
    public function __construct(
        public readonly array $pathPatterns,
        public readonly ?int $processes,
        public readonly ?string $cwdTemplate,
        public readonly ?string $labelTemplate,
        public readonly ?string $configPath,
        public readonly bool $help,
        public readonly bool $dryRun = false,
        public readonly bool $failFast = false,
        public readonly array $commands = [],
    ) {
    }
}

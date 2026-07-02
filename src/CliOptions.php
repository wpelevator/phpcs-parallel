<?php

namespace WPElevator\Pharallel;

final class CliOptions
{
    /** @param list<string> $pathPatterns */
    public function __construct(
        public readonly array $pathPatterns,
        public readonly ?string $command,
        public readonly ?int $processes,
        public readonly ?string $cwdTemplate,
        public readonly ?string $labelTemplate,
        public readonly ?string $configPath,
        public readonly bool $help,
        public readonly bool $dryRun = false,
        public readonly bool $failFast = false,
    ) {
    }
}

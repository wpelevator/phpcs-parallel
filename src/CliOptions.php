<?php

namespace WPElevator\PHPCSParallel;

final class CliOptions
{
    /**
     * @param list<string> $dirs
     * @param list<string> $configPatterns
     * @param list<string> $passthrough
     */
    public function __construct(
        public readonly array $dirs,
        public readonly array $configPatterns,
        public readonly int $processes,
        public readonly array $passthrough,
        public readonly ?string $bin,
        public readonly bool $help,
    ) {
    }
}

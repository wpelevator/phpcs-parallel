<?php

namespace WPElevator\PHPCSParallel;

final class GenericCliOptions
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
    ) {
    }
}

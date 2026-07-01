<?php

namespace WPElevator\PHPCSParallel;

final class Project
{
    public function __construct(
        public readonly string $rootDir,
        public readonly string $configFile,
    ) {
    }

    public function label(): string
    {
        return basename($this->rootDir) ?: $this->rootDir;
    }
}

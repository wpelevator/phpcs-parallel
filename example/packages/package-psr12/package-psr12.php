<?php

namespace WPElevator\RunParallel\Example;

class PackagePSR12
{
    public function __construct(
        public readonly string $rootDir,
    ) {
    }

    public function label(): string
    {
        return basename($this->rootDir);
    }
}

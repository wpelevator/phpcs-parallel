<?php

namespace WPElevator\Pharallel;

final class TaskResult
{
    public function __construct(
        public readonly string $label,
        public readonly int $exitCode,
        public readonly float $duration,
        public readonly bool $stopped = false,
    ) {
    }
}

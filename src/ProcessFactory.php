<?php

namespace WPElevator\PHPCSParallel;

interface ProcessFactory
{
    /** @param list<string> $command */
    public function start(array $command, string $cwd, string $label): Process;
}

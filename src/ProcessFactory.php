<?php

namespace WPElevator\RunParallel;

interface ProcessFactory
{
    /** @param list<string> $command */
    public function start(array $command, string $cwd, string $label): Process;
}

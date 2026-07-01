<?php

namespace WPElevator\PHPCSParallel;

final class CommandBuilder
{
    /**
     * @param list<string> $passthrough
     * @return list<string>
     */
    public function build(string $binary, Project $project, array $passthrough): array
    {
        return array_merge([$binary], $passthrough, ['--standard=' . $project->configFile, $project->rootDir]);
    }
}

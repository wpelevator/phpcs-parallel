<?php

namespace WPElevator\PHPCSParallel;

final class ProjectRunner
{
    /** @var callable(int): void */
    private $sleep;

    /** @param null|callable(int): void $sleep */
    public function __construct(
        private readonly ProcessFactory $processFactory,
        private readonly CommandBuilder $commandBuilder,
        private readonly ExitCodeAggregator $exitCodeAggregator,
        ?callable $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    /**
     * @param list<Project> $projects
     * @param list<string> $passthrough
     */
    public function run(string $binary, array $projects, int $processes, array $passthrough): int
    {
        $queue = $projects;
        $running = [];
        $exitCode = 0;

        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < $processes) {
                $project = array_shift($queue);
                assert($project instanceof Project);
                $running[] = $this->processFactory->start(
                    $this->commandBuilder->build($binary, $project, $passthrough),
                    $project->rootDir,
                    $project->label()
                );
            }

            foreach ($running as $index => $process) {
                $process->pump();
                if (! $process->isRunning()) {
                    $process->pump();
                    $exitCode = $this->exitCodeAggregator->aggregate($exitCode, $process->exitCode());
                    unset($running[$index]);
                }
            }

            $running = array_values($running);
            if ($running !== []) {
                ($this->sleep)(10000);
            }
        }

        return $exitCode;
    }
}

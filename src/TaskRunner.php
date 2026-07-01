<?php

namespace WPElevator\PHPCSParallel;

final class TaskRunner
{
    /** @var callable(int): void */
    private $sleep;

    /** @param null|callable(int): void $sleep */
    public function __construct(
        private readonly ProcessFactory $processFactory,
        private readonly CommandTemplate $commandTemplate,
        private readonly TemplateRenderer $templateRenderer,
        private readonly ExitCodeAggregator $exitCodeAggregator,
        ?callable $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    /**
     * @param list<Task> $tasks
     */
    public function run(
        array $tasks,
        string $commandTemplate,
        int $processes,
        string $invocationCwd,
        ?string $cwdTemplate = null,
        ?string $labelTemplate = null,
    ): int {
        $queue = $tasks;
        $running = [];
        $exitCode = 0;

        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < $processes) {
                $task = array_shift($queue);
                $command = $this->commandTemplate->render($commandTemplate, $task, $invocationCwd);
                $cwd = $cwdTemplate === null
                    ? $invocationCwd
                    : $this->resolveCwd(
                        $this->templateRenderer->render($cwdTemplate, $task, $invocationCwd),
                        $invocationCwd
                    );
                $label = $labelTemplate === null
                    ? $this->templateRenderer->render('{path | dirname | basename}', $task, $invocationCwd)
                    : $this->templateRenderer->render($labelTemplate, $task, $invocationCwd);

                $running[] = $this->processFactory->start($command, $cwd, $label);
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

    private function resolveCwd(string $cwd, string $invocationCwd): string
    {
        if ($cwd === '') {
            throw new \InvalidArgumentException('Rendered --cwd is empty.');
        }

        $path = $this->isAbsolutePath($cwd) ? $cwd : $invocationCwd . DIRECTORY_SEPARATOR . $cwd;
        $real = realpath($path);
        if ($real === false || ! is_dir($real)) {
            throw new \RuntimeException('Rendered --cwd is not a directory: ' . $cwd);
        }

        return $real;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}

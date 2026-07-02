<?php

namespace WPElevator\RunParallel;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

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
        private readonly ConsoleOutputInterface $output,
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
        ?string $commandTemplate,
        int $processes,
        string $invocationCwd,
        ?string $cwdTemplate = null,
        ?string $labelTemplate = null,
        bool $dryRun = false,
        bool $failFast = false,
    ): int {
        if ($dryRun) {
            return $this->dryRun($tasks, $commandTemplate, $invocationCwd, $cwdTemplate, $labelTemplate);
        }

        $queue = $tasks;
        /** @var list<array{process: Process, label: string, start: float}> $running */
        $running = [];
        /** @var list<TaskResult> $results */
        $results = [];
        $exitCode = 0;
        $interrupted = false;
        $restoreSignals = $this->trapSignals($interrupted);
        $runStart = microtime(true);

        while ($queue !== [] || $running !== []) {
            while (! $interrupted && $queue !== [] && count($running) < $processes) {
                $task = array_shift($queue);
                [$command, $cwd, $label] = $this->prepare(
                    $task,
                    $commandTemplate,
                    $invocationCwd,
                    $cwdTemplate,
                    $labelTemplate
                );

                $running[] = [
                    'process' => $this->processFactory->start($command, $cwd, $label),
                    'label' => $label,
                    'start' => microtime(true),
                ];
            }

            $stopRemaining = $interrupted;
            foreach ($running as $index => $entry) {
                $entry['process']->pump();
                if (! $entry['process']->isRunning()) {
                    $taskExitCode = $entry['process']->exitCode();
                    $results[] = new TaskResult($entry['label'], $taskExitCode, microtime(true) - $entry['start']);
                    $exitCode = $this->exitCodeAggregator->aggregate($exitCode, $taskExitCode);
                    unset($running[$index]);

                    if ($failFast && $taskExitCode !== 0) {
                        $stopRemaining = true;
                    }
                }
            }

            $running = array_values($running);

            if ($stopRemaining) {
                $queue = [];
                foreach ($running as $entry) {
                    $entry['process']->stop();
                    $entry['process']->exitCode();
                    $results[] = new TaskResult($entry['label'], 0, microtime(true) - $entry['start'], true);
                }
                $running = [];
            }

            if ($running !== []) {
                ($this->sleep)(10000);
            }
        }

        $restoreSignals();

        if ($interrupted) {
            $this->output->getErrorOutput()->write('Interrupted.' . PHP_EOL);
            $exitCode = max($exitCode, 130);
        }

        $this->writeSummary($results, microtime(true) - $runStart);

        return $exitCode;
    }

    /** @param list<Task> $tasks */
    private function dryRun(
        array $tasks,
        ?string $commandTemplate,
        string $invocationCwd,
        ?string $cwdTemplate,
        ?string $labelTemplate
    ): int {
        foreach ($tasks as $task) {
            [$command, $cwd, $label] = $this->prepare(
                $task,
                $commandTemplate,
                $invocationCwd,
                $cwdTemplate,
                $labelTemplate
            );

            $line = '[' . $label . '] $ ' . CommandFormatter::format($command);
            if ($cwd !== $invocationCwd) {
                $line .= ' (cwd: ' . $cwd . ')';
            }

            $this->output->writeln(OutputFormatter::escape($line));
        }

        return 0;
    }

    /** @return array{list<string>, string, string} */
    private function prepare(
        Task $task,
        ?string $commandTemplate,
        string $invocationCwd,
        ?string $cwdTemplate,
        ?string $labelTemplate
    ): array {
        $template = $task->command ?? $commandTemplate;
        if ($template === null) {
            throw new \InvalidArgumentException('Task has no command: ' . $task->path);
        }

        $command = $this->commandTemplate->render($template, $task, $invocationCwd);
        $cwd = $cwdTemplate === null
            ? $invocationCwd
            : $this->resolveCwd(
                $this->templateRenderer->render($cwdTemplate, $task, $invocationCwd),
                $invocationCwd
            );
        $label = $this->templateRenderer->render(
            $labelTemplate ?? '{path | dirname | basename}',
            $task,
            $invocationCwd
        );

        return [$command, $cwd, $label];
    }

    /** @param list<TaskResult> $results */
    private function writeSummary(array $results, float $elapsed): void
    {
        if ($results === []) {
            return;
        }

        $output = $this->output->getErrorOutput();
        $failed = 0;
        $stopped = 0;

        $output->writeln('');
        $output->writeln('Summary:');

        foreach ($results as $result) {
            $label = OutputFormatter::escape($result->label);
            $duration = self::formatDuration($result->duration);

            if ($result->stopped) {
                $stopped++;
                $output->writeln(sprintf('  <fg=yellow>- %s (stopped after %s)</>', $label, $duration));
            } elseif ($result->exitCode === 0) {
                $output->writeln(sprintf('  <fg=green>✓ %s (%s)</>', $label, $duration));
            } else {
                $failed++;
                $output->writeln(sprintf('  <fg=red>✗ %s (%s, exit %d)</>', $label, $duration, $result->exitCode));
            }
        }

        $counts = sprintf('%d passed', count($results) - $failed - $stopped);
        if ($failed > 0) {
            $counts .= sprintf(', %d failed', $failed);
        }
        if ($stopped > 0) {
            $counts .= sprintf(', %d stopped', $stopped);
        }

        $output->writeln(sprintf('%s (%s)', $counts, self::formatDuration($elapsed)));
    }

    private static function formatDuration(float $seconds): string
    {
        if ($seconds >= 60) {
            return sprintf('%dm %ds', intdiv((int) $seconds, 60), (int) $seconds % 60);
        }

        return sprintf('%.1fs', $seconds);
    }

    /** @return callable(): void */
    private function trapSignals(bool &$interrupted): callable
    {
        if (! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            return static function (): void {
            };
        }

        $wasAsync = pcntl_async_signals();
        pcntl_async_signals(true);
        $handler = static function () use (&$interrupted): void {
            $interrupted = true;
        };
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);

        return static function () use ($wasAsync): void {
            pcntl_signal(SIGINT, SIG_DFL);
            pcntl_signal(SIGTERM, SIG_DFL);
            pcntl_async_signals($wasAsync);
        };
    }

    private function resolveCwd(string $cwd, string $invocationCwd): string
    {
        if ($cwd === '') {
            throw new \InvalidArgumentException('Rendered --cwd is empty.');
        }

        $path = Path::isAbsolute($cwd) ? $cwd : $invocationCwd . DIRECTORY_SEPARATOR . $cwd;
        $real = realpath($path);
        if ($real === false || ! is_dir($real)) {
            throw new \RuntimeException('Rendered --cwd is not a directory: ' . $cwd);
        }

        return $real;
    }
}

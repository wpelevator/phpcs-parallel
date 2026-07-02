<?php

namespace WPElevator\RunParallel;

use Symfony\Component\Console\Output\ConsoleOutputInterface;

final class Application
{
    public function __construct(
        private readonly ArgsParser $argsParser,
        private readonly PathResolver $pathResolver,
        private readonly ConfigLoader $configLoader,
        private readonly ProcessFactory $processFactory,
        private readonly ExitCodeAggregator $exitCodeAggregator,
        private readonly HelpFormatter $helpFormatter,
        private readonly ConsoleOutputInterface $output,
    ) {
    }

    /** @param list<string> $argv */
    public function run(array $argv, ?string $cwd = null): int
    {
        try {
            $cwd = realpath($cwd ?? (getcwd() ?: '.')) ?: ($cwd ?? '.');
            $options = $this->argsParser->parse(array_slice($argv, 1));

            if ($options->help) {
                $this->output->write($this->helpFormatter->format());
                return 0;
            }

            $config = $this->configLoader->load($options->configPath, $cwd);
            $processes = $options->processes ?? $this->processesDefault($config);
            $cwdTemplate = $options->cwdTemplate ?? $this->stringDefault($config, 'cwd');
            $labelTemplate = $options->labelTemplate ?? $this->stringDefault($config, 'label');
            $failFast = $options->failFast || $this->boolDefault($config, 'fail-fast');

            $pathPatterns = $options->pathPatterns !== []
                ? $options->pathPatterns
                : $this->pathPatternDefault($config);
            $commands = $options->commands !== []
                ? $options->commands
                : $this->commandsDefault($config);

            if ($commands === []) {
                throw new \InvalidArgumentException('At least one command is required.');
            }

            if ($pathPatterns !== []) {
                $command = null;
                $matchedTasks = $this->pathResolver->resolve($pathPatterns, $cwd);
                if ($matchedTasks === []) {
                    $this->output->getErrorOutput()->write("No paths matched.\n");
                    return 3;
                }

                $labelTemplate ??= count($commands) > 1
                    ? '{path | dirname | basename}:{command | slug}'
                    : null;
                $tasks = [];
                $index = 0;
                foreach ($matchedTasks as $matchedTask) {
                    foreach ($commands as $taskCommand) {
                        $tasks[] = new Task($matchedTask->path, $index++, $matchedTask->variables, $taskCommand);
                    }
                }
            } else {
                $command = null;
                $labelTemplate ??= '{path | slug}';
                $tasks = [];
                foreach ($commands as $index => $taskCommand) {
                    $tasks[] = new Task($taskCommand, $index, command: $taskCommand);
                }
            }

            $templateRenderer = new TemplateRenderer($config->filters, $config->variables);
            $taskRunner = new TaskRunner(
                $this->processFactory,
                new CommandTemplate($templateRenderer),
                $templateRenderer,
                $this->exitCodeAggregator,
                $this->output
            );

            return $taskRunner->run(
                $tasks,
                $command,
                $processes,
                $cwd,
                $cwdTemplate,
                $labelTemplate,
                $options->dryRun,
                $failFast
            );
        } catch (\Throwable $e) {
            $this->output->getErrorOutput()->write($e->getMessage() . PHP_EOL);
            return 3;
        }
    }

    private function stringDefault(RunParallelConfig $config, string $name): ?string
    {
        if (! array_key_exists($name, $config->defaults)) {
            return null;
        }

        $value = $config->defaults[$name];
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            throw new \RuntimeException('Config default must be scalar or stringable: ' . $name);
        }

        return (string) $value;
    }

    /** @return list<string> */
    private function pathPatternDefault(RunParallelConfig $config): array
    {
        if (! array_key_exists('path-pattern', $config->defaults)) {
            return [];
        }

        $value = $config->defaults['path-pattern'];
        if (is_array($value)) {
            $patterns = [];
            foreach ($value as $pattern) {
                if (! is_scalar($pattern) && ! $pattern instanceof \Stringable) {
                    throw new \RuntimeException('Config default path-pattern values must be scalar or stringable.');
                }
                $patterns[] = (string) $pattern;
            }

            return $patterns;
        }

        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            throw new \RuntimeException('Config default path-pattern must be scalar, stringable, or an array.');
        }

        return [(string) $value];
    }

    /** @return list<string> */
    private function commandsDefault(RunParallelConfig $config): array
    {
        if (! array_key_exists('command', $config->defaults)) {
            return [];
        }

        $value = $config->defaults['command'];
        if (is_array($value)) {
            $commands = [];
            foreach ($value as $command) {
                if (! is_scalar($command) && ! $command instanceof \Stringable) {
                    throw new \RuntimeException('Config default command values must be scalar or stringable.');
                }
                $commands[] = (string) $command;
            }

            return $commands;
        }

        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            throw new \RuntimeException('Config default command must be scalar, stringable, or an array.');
        }

        return [(string) $value];
    }

    private function processesDefault(RunParallelConfig $config): int
    {
        $value = $this->stringDefault($config, 'processes');

        return $value === null ? CpuCount::detect() : Processes::parse($value);
    }

    private function boolDefault(RunParallelConfig $config, string $name): bool
    {
        if (! array_key_exists($name, $config->defaults)) {
            return false;
        }

        $value = $config->defaults[$name];
        if (! is_bool($value)) {
            throw new \RuntimeException('Config default must be a boolean: ' . $name);
        }

        return $value;
    }
}

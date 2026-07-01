<?php

namespace WPElevator\PHPCSParallel;

use Symfony\Component\Console\Output\ConsoleOutputInterface;

final class GenericApplication
{
    public function __construct(
        private readonly GenericArgsParser $argsParser,
        private readonly PathResolver $pathResolver,
        private readonly ConfigLoader $configLoader,
        private readonly ProcessFactory $processFactory,
        private readonly ExitCodeAggregator $exitCodeAggregator,
        private readonly GenericHelpFormatter $helpFormatter,
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
            $command = $options->command ?? $this->stringDefault($config, 'command');
            if ($command === null || $command === '') {
                throw new \InvalidArgumentException('--command is required.');
            }

            $pathPatterns = $options->pathPatterns !== []
                ? $options->pathPatterns
                : $this->pathPatternDefault($config);
            $processes = $options->processes ?? $this->intDefault($config, 'processes', 1);
            $cwdTemplate = $options->cwdTemplate ?? $this->stringDefault($config, 'cwd');
            $labelTemplate = $options->labelTemplate ?? $this->stringDefault($config, 'label');

            $tasks = $this->pathResolver->resolve($pathPatterns, $cwd);
            if ($tasks === []) {
                $this->output->getErrorOutput()->write("No paths matched.\n");
                return 3;
            }

            $templateRenderer = new TemplateRenderer($config->filters, $config->variables);
            $taskRunner = new TaskRunner(
                $this->processFactory,
                new CommandTemplate($templateRenderer),
                $templateRenderer,
                $this->exitCodeAggregator
            );

            return $taskRunner->run(
                $tasks,
                $command,
                $processes,
                $cwd,
                $cwdTemplate,
                $labelTemplate
            );
        } catch (\Throwable $e) {
            $this->output->getErrorOutput()->write($e->getMessage() . PHP_EOL);
            return 3;
        }
    }

    private function stringDefault(PharallelConfig $config, string $name): ?string
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
    private function pathPatternDefault(PharallelConfig $config): array
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

    private function intDefault(PharallelConfig $config, string $name, int $fallback): int
    {
        if (! array_key_exists($name, $config->defaults)) {
            return $fallback;
        }

        $value = $config->defaults[$name];
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            throw new \RuntimeException('Config default must be scalar or stringable: ' . $name);
        }

        return max(1, (int) (string) $value);
    }
}

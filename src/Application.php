<?php

namespace WPElevator\PHPCSParallel;

use Symfony\Component\Console\Output\ConsoleOutputInterface;

final class Application
{
    public function __construct(
        private readonly ArgsParser $argsParser,
        private readonly ProjectResolver $projectResolver,
        private readonly BinaryResolver $binaryResolver,
        private readonly ProjectRunner $projectRunner,
        private readonly HelpFormatter $helpFormatter,
        private readonly ConsoleOutputInterface $output,
    ) {
    }

    /** @param list<string> $argv */
    public function run(string $tool, array $argv, ?string $cwd = null): int
    {
        try {
            $cwd ??= getcwd() ?: '.';
            $options = $this->argsParser->parse(array_slice($argv, 1));

            if ($options->help) {
                $this->output->write($this->helpFormatter->format($tool));
                return 0;
            }

            $projects = $this->projectResolver->resolve($options->dirs, $options->configPatterns, $cwd);
            if ($projects === []) {
                $this->output->getErrorOutput()->write("No PHPCS config files found.\n");
                return 3;
            }

            $binary = $this->binaryResolver->resolve($tool, $options->bin, $cwd);
            return $this->projectRunner->run($binary, $projects, $options->processes, $options->passthrough);
        } catch (\Throwable $e) {
            $this->output->getErrorOutput()->write($e->getMessage() . PHP_EOL);
            return 3;
        }
    }
}

<?php

namespace WPElevator\PHPCSParallel;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

final class SymfonyProcessFactory implements ProcessFactory
{
    public function __construct(private readonly ConsoleOutputInterface $output)
    {
    }

    /** @param list<string> $command */
    public function start(array $command, string $cwd, string $label): Process
    {
        $process = new SymfonyProcess($command, $cwd, null, null, null);
        $process->start();

        $this->output->getErrorOutput()->write(
            '[' . $label . '] $ ' . implode(' ', array_map('escapeshellarg', $command)) . PHP_EOL
        );

        return new Process($process, $label, $this->output);
    }
}

<?php

namespace WPElevator\Pharallel;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

final class SymfonyProcessFactory implements ProcessFactory
{
    private const COLORS = ['36', '32', '33', '35', '34'];

    private int $started = 0;

    public function __construct(private readonly ConsoleOutputInterface $output)
    {
    }

    /** @param list<string> $command */
    public function start(array $command, string $cwd, string $label): Process
    {
        $process = new SymfonyProcess($command, $cwd, null, null, null);
        $process->start();

        $prefix = $this->prefix($label);
        $this->output->getErrorOutput()->write(
            $prefix . '$ ' . CommandFormatter::format($command) . PHP_EOL,
            false,
            OutputInterface::OUTPUT_RAW
        );

        return new Process($process, $prefix, $this->output);
    }

    private function prefix(string $label): string
    {
        $index = $this->started++;

        if (! $this->output->isDecorated() || ! $this->output->getErrorOutput()->isDecorated()) {
            return '[' . $label . '] ';
        }

        $color = self::COLORS[$index % count(self::COLORS)];

        return "\033[" . $color . 'm[' . $label . "]\033[39m ";
    }
}

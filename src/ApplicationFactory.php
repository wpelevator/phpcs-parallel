<?php

namespace WPElevator\PHPCSParallel;

use Symfony\Component\Console\Output\ConsoleOutput;

final class ApplicationFactory
{
    public function create(): Application
    {
        $output = new ConsoleOutput();
        $commandBuilder = new CommandBuilder();
        $exitCodeAggregator = new ExitCodeAggregator();

        return new Application(
            new ArgsParser(),
            new ProjectResolver(),
            new BinaryResolver(),
            new ProjectRunner(new SymfonyProcessFactory($output), $commandBuilder, $exitCodeAggregator),
            new HelpFormatter(),
            $output
        );
    }
}

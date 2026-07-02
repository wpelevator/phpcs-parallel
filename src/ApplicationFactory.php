<?php

namespace WPElevator\RunParallel;

use Symfony\Component\Console\Output\ConsoleOutput;

final class ApplicationFactory
{
    public function create(): Application
    {
        $output = new ConsoleOutput();

        return new Application(
            new ArgsParser(),
            new PathResolver(),
            new ConfigLoader(),
            new SymfonyProcessFactory($output),
            new ExitCodeAggregator(),
            new HelpFormatter(),
            $output
        );
    }
}

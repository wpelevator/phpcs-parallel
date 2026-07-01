<?php

namespace WPElevator\PHPCSParallel;

use Symfony\Component\Console\Output\ConsoleOutput;

final class ApplicationFactory
{
    public function create(): GenericApplication
    {
        $output = new ConsoleOutput();

        return new GenericApplication(
            new GenericArgsParser(),
            new PathResolver(),
            new ConfigLoader(),
            new SymfonyProcessFactory($output),
            new ExitCodeAggregator(),
            new GenericHelpFormatter(),
            $output
        );
    }
}
